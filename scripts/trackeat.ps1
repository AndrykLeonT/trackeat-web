param(
    [ValidateSet('Base','Site')] [string] $Mode = 'Site',
    [switch] $Stop,
    [switch] $CheckOnly,
    [switch] $NoBrowser,
    [ValidateRange(1,65535)] [int] $BackendPort = 8000,
    [ValidateRange(1,65535)] [int] $FrontendPort = 5173,
    [ValidateRange(0,3600)] [int] $RunSeconds = 0
)
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$backend = Join-Path $root 'backend'
$helper = Join-Path $backend 'scripts/team-database.php'
$processes = @()
$oldAppUrl = $env:APP_URL
$hotFile = Join-Path $backend 'public/hot'
$oldHot = if (Test-Path -LiteralPath $hotFile) { [IO.File]::ReadAllText($hotFile) } else { $null }

function Free-Port([int] $Preferred) {
    for ($candidate = $Preferred; $candidate -le [Math]::Min(65535, $Preferred + 20); $candidate++) {
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $candidate)
        try { $listener.Start(); return $candidate } catch { } finally { $listener.Stop() }
    }
    throw "No hay puertos disponibles cerca de $Preferred."
}
function Quote-Argument([string] $Value) {
    return '"' + [regex]::Replace([regex]::Replace($Value, '(\\*)"', '$1$1\"'), '(\\+)$', '$1$1') + '"'
}
function Initialize-Php {
    $command = Get-Command php.exe -ErrorAction SilentlyContinue
    $script:php = if ($command) { $command.Source } elseif (Test-Path 'C:\xampp\php\php.exe') { 'C:\xampp\php\php.exe' } else {
        Get-ChildItem 'C:\laragon\bin\php\*\php.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
    }
    if (!$script:php) { throw 'Instala PHP 8.2+ y agrega php.exe al PATH.' }
    if (!(Test-Path -LiteralPath (Join-Path $backend 'vendor/autoload.php'))) { throw 'Ejecuta composer install dentro de backend.' }
    if (!(Test-Path -LiteralPath $helper)) { throw 'Falta backend/scripts/team-database.php. Recupera ese archivo del proyecto.' }
    $script:phpFlags = @()
    $modules = (& $script:php -r 'echo json_encode(get_loaded_extensions());') | ConvertFrom-Json
    foreach ($extension in @('pdo_pgsql','bcmath','mbstring','openssl','fileinfo')) {
        if ($modules -notcontains $extension) {
            $dll = Join-Path (Split-Path $script:php) "ext/php_$extension.dll"
            if (!(Test-Path -LiteralPath $dll)) { throw "Falta la extension PHP $extension. Revisa php --ini." }
            $script:phpFlags += @('-d', "extension=$dll")
        }
    }
}
function Artisan([string[]] $Arguments) {
    Push-Location $backend
    try {
        & $script:php @script:phpFlags artisan @Arguments
        if ($LASTEXITCODE -ne 0) { throw "Fallo Laravel: artisan $($Arguments -join ' ')" }
    } finally { Pop-Location }
}
function Database-Helper([hashtable] $Payload) {
    $Payload | ConvertTo-Json -Compress | & $script:php @script:phpFlags $helper
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo completar la operacion de base de datos.' }
}
function New-Secret {
    $bytes = New-Object byte[] 24
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $random.GetBytes($bytes) } finally { $random.Dispose() }
    return ([BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant()
}
function Private-File([string] $Path, [string] $Contents) {
    if (!(Test-Path -LiteralPath $Path)) { [IO.File]::WriteAllText($Path, '') }
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    & icacls.exe $Path '/inheritance:r' '/grant:r' "*${sid}:(F)" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron proteger las credenciales locales.' }
    [IO.File]::WriteAllText($Path, $Contents, [Text.UTF8Encoding]::new($false))
}
function Start-Server([string] $Exe, [string[]] $Arguments, [string] $Directory, [string] $Name) {
    $logs = Join-Path $backend 'storage/logs'
    New-Item -ItemType Directory -Path $logs -Force | Out-Null
    $quoted = ($Arguments | ForEach-Object { Quote-Argument $_ }) -join ' '
    return Start-Process -FilePath $Exe -ArgumentList $quoted -WorkingDirectory $Directory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logs "$Name.stdout.log") -RedirectStandardError (Join-Path $logs "$Name.stderr.log")
}

try {
    if ($Mode -eq 'Base') {
        $local = Join-Path $backend 'storage/app/private/local-postgres'
        $data = Join-Path $local 'data'
        $stateFile = Join-Path $local 'connection.json'
        $state = if (Test-Path -LiteralPath $stateFile) { Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json } else { $null }
        $pg = if ($state) { $state.postgres_bin } else {
            $command = Get-Command pg_ctl.exe -ErrorAction SilentlyContinue
            if ($command) { Split-Path $command.Source } else {
                $install = Get-ChildItem (Join-Path $env:ProgramFiles 'PostgreSQL') -Directory -ErrorAction SilentlyContinue | Where-Object { $_.Name -match '^\d+$' -and [int]$_.Name -ge 15 } | Sort-Object { [int]$_.Name } -Descending | Select-Object -First 1
                if ($install) { Join-Path $install.FullName 'bin' }
            }
        }
        foreach ($exe in @('pg_ctl.exe','initdb.exe','psql.exe','createdb.exe')) {
            if (!$pg -or !(Test-Path -LiteralPath (Join-Path $pg $exe))) { throw 'Instala PostgreSQL 15+ en esta computadora.' }
        }
        if ($Stop) {
            if (!(Test-Path -LiteralPath (Join-Path $data 'PG_VERSION'))) { throw 'Todavia no existe la base local.' }
            & (Join-Path $pg 'pg_ctl.exe') -D $data status *> $null
            if ($LASTEXITCODE -eq 0) {
                & (Join-Path $pg 'pg_ctl.exe') -D $data -m fast -w stop
                if ($LASTEXITCODE -ne 0) { throw 'No se pudo detener PostgreSQL.' }
            }
            Write-Host 'Base detenida. Los datos se conservaron.' -ForegroundColor Green
            exit 0
        }
        Initialize-Php
        if (!$state) {
            if (Test-Path -LiteralPath (Join-Path $data 'PG_VERSION')) { throw 'Falta connection.json de la base existente. No se borrara ni reinicializara.' }
            New-Item -ItemType Directory -Path $local -Force | Out-Null
            $state = [pscustomobject]@{ port = (Free-Port 55433); postgres_bin = $pg; password = (New-Secret); admin_password = (New-Secret) }
            Private-File $stateFile ($state | ConvertTo-Json)
        }
        if ($state.password -notmatch '^[0-9a-f]{48}$' -or $state.admin_password -notmatch '^[0-9a-f]{48}$' -or [int]$state.port -lt 1 -or [int]$state.port -gt 65535) { throw 'La configuracion local no tiene el formato esperado.' }
        if (!(Test-Path -LiteralPath (Join-Path $data 'PG_VERSION'))) {
            $passwordFile = Join-Path $local 'init-password.tmp'
            try {
                Private-File $passwordFile $state.admin_password
                & (Join-Path $pg 'initdb.exe') -D $data -U trackeat_admin --auth-host=scram-sha-256 --auth-local=scram-sha-256 --encoding=UTF8 --locale=C "--pwfile=$passwordFile"
                if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear PostgreSQL local.' }
            } finally { if (Test-Path -LiteralPath $passwordFile) { Remove-Item -LiteralPath $passwordFile } }
        }
        [IO.File]::WriteAllText((Join-Path $data 'postgresql.auto.conf'), "listen_addresses = '127.0.0.1'`nport = $($state.port)`npassword_encryption = 'scram-sha-256'`ntimezone = 'UTC'`n")
        [IO.File]::WriteAllText((Join-Path $data 'pg_hba.conf'), "host all trackeat_admin 127.0.0.1/32 scram-sha-256`nhost trackeat trackeat 127.0.0.1/32 scram-sha-256`n")
        & (Join-Path $pg 'pg_ctl.exe') -D $data status *> $null
        if ($LASTEXITCODE -ne 0) {
            $argsQuoted = (@('-D', $data, '-l', (Join-Path $local 'server.log'), '-w', 'start') | ForEach-Object { Quote-Argument $_ }) -join ' '
            $starter = Start-Process (Join-Path $pg 'pg_ctl.exe') -ArgumentList $argsQuoted -WindowStyle Hidden -PassThru
            if (!$starter.WaitForExit(65000) -or $starter.ExitCode -ne 0) { throw "No inicio PostgreSQL. Revisa $local/server.log." }
        }
        $oldPassword = $env:PGPASSWORD
        try {
            $env:PGPASSWORD = $state.admin_password
            $sqlArgs = @('-h','127.0.0.1','-p',[string]$state.port,'-U','trackeat_admin','-d','postgres','-w','-v','ON_ERROR_STOP=1','-t','-A')
            $role = & (Join-Path $pg 'psql.exe') @sqlArgs -c "SELECT 1 FROM pg_roles WHERE rolname='trackeat'"
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo acceder al administrador local.' }
            if (($role -join '').Trim() -ne '1') {
                "CREATE ROLE trackeat LOGIN PASSWORD '$($state.password)' NOSUPERUSER NOCREATEDB NOCREATEROLE;" | & (Join-Path $pg 'psql.exe') @sqlArgs
                if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el usuario local.' }
            }
            $database = & (Join-Path $pg 'psql.exe') @sqlArgs -c "SELECT 1 FROM pg_database WHERE datname='trackeat'"
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar PostgreSQL.' }
            if (($database -join '').Trim() -ne '1') {
                & (Join-Path $pg 'createdb.exe') -h 127.0.0.1 -p $state.port -U trackeat_admin -w -O trackeat trackeat
                if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la base trackeat.' }
            }
        } finally { $env:PGPASSWORD = $oldPassword }
        Database-Helper @{ mode='configure'; host='127.0.0.1'; port=$state.port; database='trackeat'; username='trackeat'; password=$state.password }
        $localEnv = @{ DB_CONNECTION='pgsql'; DB_HOST='127.0.0.1'; DB_PORT=[string]$state.port; DB_DATABASE='trackeat'; DB_USERNAME='trackeat'; DB_PASSWORD=$state.password; DB_URL=''; APP_ENV='local' }
        $previousEnv = @{}
        foreach ($key in $localEnv.Keys) { $previousEnv[$key] = [Environment]::GetEnvironmentVariable($key,'Process'); [Environment]::SetEnvironmentVariable($key,$localEnv[$key],'Process') }
        try {
            Artisan @('config:clear')
            Artisan @('migrate','--force')
            Artisan @('db:seed')
        } finally { foreach ($key in $previousEnv.Keys) { [Environment]::SetEnvironmentVariable($key,$previousEnv[$key],'Process') } }
        Write-Host 'Base local lista. Ahora ejecuta iniciar.bat.' -ForegroundColor Green
    } else {
        Initialize-Php
        $node = Get-Command node.exe -ErrorAction SilentlyContinue
        $vite = Join-Path $root 'frontend/node_modules/vite/bin/vite.js'
        if (!$node -or !(Test-Path -LiteralPath $vite)) { throw 'Instala Node.js y ejecuta pnpm install dentro de frontend.' }
        Artisan @('config:clear')
        Database-Helper @{mode='check'}
        if ($CheckOnly) { Write-Host 'Conexion y requisitos correctos.' -ForegroundColor Green; exit 0 }
        # El catalogo usa archivos compilados propios, tambien durante el desarrollo con Vite.
        Push-Location (Join-Path $root 'frontend')
        try {
            & $node.Source $vite build --config vite.personal.config.js
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo compilar el catalogo de personal.' }
        } finally { Pop-Location }
        $BackendPort = Free-Port $BackendPort
        if ($FrontendPort -eq $BackendPort) { $FrontendPort++ }
        $FrontendPort = Free-Port $FrontendPort
        $url = "http://127.0.0.1:$BackendPort"
        $env:APP_URL = $url
        $router = Join-Path $backend 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
        $processes += Start-Server $script:php (@($script:phpFlags) + @('-S',"127.0.0.1:$BackendPort",$router)) (Join-Path $backend 'public') 'laravel'
        $processes += Start-Server $node.Source @($vite,'--host','127.0.0.1','--port',[string]$FrontendPort,'--strictPort') (Join-Path $root 'frontend') 'vite'
        $processes += Start-Server $script:php (@($script:phpFlags) + @('artisan','queue:work','--sleep=1','--tries=1')) $backend 'queue'
        $ready = $false
        $deadline = [DateTime]::UtcNow.AddSeconds(30)
        while ([DateTime]::UtcNow -lt $deadline) {
            foreach ($process in $processes) { if ($process.HasExited) { throw 'Un servidor termino. Revisa backend/storage/logs/*.stderr.log.' } }
            try {
                $page = Invoke-WebRequest "$url/" -UseBasicParsing -TimeoutSec 2
                $client = Invoke-WebRequest "http://127.0.0.1:$FrontendPort/@vite/client" -UseBasicParsing -TimeoutSec 2
                if ($page.StatusCode -eq 200 -and $client.StatusCode -eq 200) { $ready = $true; break }
            } catch { Start-Sleep -Milliseconds 300 }
        }
        if (!$ready) { throw 'El sitio no estuvo listo en 30 segundos. Revisa los logs.' }
        Write-Host "Sitio listo: $url (Vite: $FrontendPort). Ctrl+C para detener." -ForegroundColor Green
        if (!$NoBrowser) { Start-Process $url }
        $stopAt = if ($RunSeconds) { [DateTime]::UtcNow.AddSeconds($RunSeconds) } else { [DateTime]::MaxValue }
        while ([DateTime]::UtcNow -lt $stopAt) {
            foreach ($process in $processes) { if ($process.HasExited) { throw 'Un servidor se detuvo. Revisa los logs.' } }
            Start-Sleep -Seconds 1
        }
    }
} catch { Write-Host $_.Exception.Message -ForegroundColor Red; exit 1 }
finally {
    foreach ($process in $processes) { if (!$process.HasExited) { try { & taskkill.exe /PID $process.Id /T /F *> $null } catch { } } }
    $env:APP_URL = $oldAppUrl
    if ($processes.Count -gt 0 -and (Test-Path -LiteralPath $hotFile) -and [IO.File]::ReadAllText($hotFile) -match ":$FrontendPort/?\s*$") {
        if ($null -ne $oldHot) { [IO.File]::WriteAllText($hotFile,$oldHot,[Text.UTF8Encoding]::new($false)) } else { Remove-Item -LiteralPath $hotFile }
    }
}
