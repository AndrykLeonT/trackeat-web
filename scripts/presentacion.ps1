param(
    [switch] $Tunnel,
    [switch] $SkipBuild,
    [switch] $TestDatabase,
    [ValidateRange(1,65535)] [int] $WebPort = 8000,
    [ValidateRange(1,65535)] [int] $SocketPort = 8080,
    [string] $WebUrl = 'http://localhost:8000',
    [string] $SocketUrl = 'http://localhost:8080',
    [ValidateRange(0,7200)] [int] $RunSeconds = 0
)
$ErrorActionPreference = 'Stop'
$taskRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskBackend = Join-Path $taskRoot 'backend'
$taskProcesses = @()
$taskEnv = @{}
$taskHot = Join-Path $taskBackend 'public/hot'
$taskOldHot = if (Test-Path -LiteralPath $taskHot) { [IO.File]::ReadAllText($taskHot) } else { $null }
function Set-TaskEnv([string] $Name, [string] $Value) {
    if (!$taskEnv.ContainsKey($Name)) { $taskEnv[$Name] = [Environment]::GetEnvironmentVariable($Name, 'Process') }
    [Environment]::SetEnvironmentVariable($Name, $Value, 'Process')
}
function Quote-TaskArg([string] $Value) { '"' + [regex]::Replace([regex]::Replace($Value, '(\\*)"', '$1$1\"'), '(\\+)$', '$1$1') + '"' }
function Start-TaskProcess([string] $Exe, [string[]] $Arguments, [string] $Directory, [string] $Name) {
    $logs = Join-Path $taskBackend 'storage/logs'
    $quoted = ($Arguments | ForEach-Object { Quote-TaskArg $_ }) -join ' '
    Start-Process -FilePath $Exe -ArgumentList $quoted -WorkingDirectory $Directory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logs "$Name.stdout.log") -RedirectStandardError (Join-Path $logs "$Name.stderr.log")
}
function Task-Artisan([string[]] $Arguments) {
    Push-Location $taskBackend
    try { & $taskPhp @taskFlags artisan @Arguments; if ($LASTEXITCODE -ne 0) { throw "Fallo artisan $($Arguments -join ' ')" } }
    finally { Pop-Location }
}
function Wait-Tunnel([string] $Name, $Process) {
    $log = Join-Path $taskBackend "storage/logs/$Name.stderr.log"
    $deadline = [DateTime]::UtcNow.AddSeconds(45)
    while ([DateTime]::UtcNow -lt $deadline) {
        if ($Process.HasExited) { throw "El tunel $Name termino. Revisa $log" }
        [string] $contents = if (Test-Path -LiteralPath $log) { Get-Content -LiteralPath $log -Raw } else { '' }
        if ([string]::IsNullOrWhiteSpace($contents)) { Start-Sleep -Milliseconds 300; continue }
        $match = [regex]::Match($contents, 'https://[a-z0-9-]+\.trycloudflare\.com')
        if ($match.Success -and $contents -match 'Registered tunnel connection') { return $match.Value }
        Start-Sleep -Milliseconds 300
    }
    throw "El tunel $Name no se registro en 45 segundos. Revisa $log"
}
try {
    if ($WebPort -eq $SocketPort) { throw 'WebPort y SocketPort deben ser diferentes.' }
    if ($WebUrl -eq 'http://localhost:8000') { $WebUrl = "http://localhost:$WebPort" }
    if ($SocketUrl -eq 'http://localhost:8080') { $SocketUrl = "http://localhost:$SocketPort" }
    foreach ($port in @($WebPort,$SocketPort)) {
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $port)
        try { $listener.Start() } catch { throw "El puerto $port no esta disponible. Elige otro con -WebPort o -SocketPort." } finally { $listener.Stop() }
    }
    $taskPhp = (Get-Command php.exe -ErrorAction Stop).Source
    $taskPhpDir = Split-Path $taskPhp
    $taskFlags = @('-d', "extension_dir=$taskPhpDir/ext")
    $modules = (& $taskPhp -r 'echo json_encode(get_loaded_extensions());') | ConvertFrom-Json
    foreach ($extension in @('pdo_pgsql','bcmath','mbstring','openssl','fileinfo','curl')) {
        if ($modules -notcontains $extension) { $taskFlags += @('-d', "extension=$extension") }
    }
    if ($TestDatabase) {
        Set-TaskEnv 'DB_DATABASE' 'trackeat_demo_test'
        Set-TaskEnv 'DB_URL' ''
    }
    Set-TaskEnv 'APP_DEBUG' 'false'
    Set-TaskEnv 'TRACKEAT_DEMO_ENABLED' 'true'
    Set-TaskEnv 'BROADCAST_CONNECTION' 'reverb'
    Set-TaskEnv 'QUEUE_CONNECTION' 'database'
    # Cada presentacion consume sus eventos con su propia configuracion de Reverb.
    Set-TaskEnv 'DB_QUEUE' "trackeat-presentacion-$WebPort-$SocketPort"
    Set-TaskEnv 'REVERB_APP_ID' 'trackeat'
    Set-TaskEnv 'REVERB_APP_KEY' 'trackeat-local'
    $bytes = New-Object byte[] 32
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $random.GetBytes($bytes) } finally { $random.Dispose() }
    Set-TaskEnv 'REVERB_APP_SECRET' ([BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant())
    Set-TaskEnv 'REVERB_HOST' '127.0.0.1'
    Set-TaskEnv 'REVERB_PORT' ([string]$SocketPort)
    Set-TaskEnv 'REVERB_SCHEME' 'http'
    Set-TaskEnv 'REVERB_SERVER_HOST' '127.0.0.1'
    Set-TaskEnv 'REVERB_SERVER_PORT' ([string]$SocketPort)
    if (!$SkipBuild) {
        Push-Location (Join-Path $taskRoot 'frontend')
        try { & pnpm.cmd build; if ($LASTEXITCODE -ne 0) { throw 'Fallo la compilacion.' } } finally { Pop-Location }
    }
    foreach ($artifact in @('build/manifest.json','personal/index.html','menu/index.html')) {
        if (!(Test-Path -LiteralPath (Join-Path $taskBackend "public/$artifact"))) { throw "Falta compilar $artifact. Ejecuta pnpm build en frontend." }
    }
    if (Test-Path -LiteralPath $taskHot) { Remove-Item -LiteralPath $taskHot }
    if ($Tunnel) {
        $cloudflared = (Get-Command cloudflared -ErrorAction Stop).Source
        if ([IO.Path]::GetExtension($cloudflared) -ne '.exe') {
            $binary = Join-Path (Split-Path $cloudflared) 'node_modules/cloudflared/bin/cloudflared.exe'
            if (!(Test-Path -LiteralPath $binary)) { throw 'No se encontro cloudflared.exe. Reinstala npm install -g cloudflared.' }
            $cloudflared = $binary
        }
        $webTunnel = Start-TaskProcess $cloudflared @('tunnel','--protocol','http2','--url',"http://127.0.0.1:$WebPort") $taskRoot 'tunnel-web'
        $taskProcesses += $webTunnel
        $socketTunnel = Start-TaskProcess $cloudflared @('tunnel','--protocol','http2','--url',"http://localhost:$SocketPort") $taskRoot 'tunnel-socket'
        $taskProcesses += $socketTunnel
        $WebUrl = Wait-Tunnel 'tunnel-web' $webTunnel
        $SocketUrl = Wait-Tunnel 'tunnel-socket' $socketTunnel
    }
    foreach ($value in @($WebUrl,$SocketUrl)) {
        $uri = [uri]$value
        if (!$uri.IsAbsoluteUri -or $uri.Scheme -notin @('http','https') -or $uri.AbsolutePath -ne '/' -or $uri.Query -or $uri.Fragment) { throw 'Usa URLs http/https sin rutas, consultas ni fragmentos.' }
    }
    Set-TaskEnv 'APP_URL' $WebUrl.TrimEnd('/')
    Set-TaskEnv 'TRACKEAT_SOCKET_URL' $SocketUrl.TrimEnd('/')
    Set-TaskEnv 'REVERB_ALLOWED_ORIGINS' "localhost,127.0.0.1,$(([uri]$WebUrl).Host)"
    Task-Artisan @('config:clear')
    Task-Artisan @('migrate','--force')
    $router = Join-Path $taskBackend 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
    $taskProcesses += Start-TaskProcess $taskPhp (@($taskFlags) + @('-S',"127.0.0.1:$WebPort",$router)) (Join-Path $taskBackend 'public') 'presentacion-web'
    $taskProcesses += Start-TaskProcess $taskPhp (@($taskFlags) + @('artisan','reverb:start','--host=127.0.0.1',"--port=$SocketPort")) $taskBackend 'presentacion-reverb'
    $taskProcesses += Start-TaskProcess $taskPhp (@($taskFlags) + @('artisan','queue:work','--sleep=1','--tries=5','--timeout=30','--backoff=1')) $taskBackend 'presentacion-queue'
    $deadline = [DateTime]::UtcNow.AddSeconds(30)
    $ready = $false
    while ([DateTime]::UtcNow -lt $deadline) {
        foreach ($process in $taskProcesses) { if ($process.HasExited) { throw 'Un proceso termino. Revisa backend/storage/logs/presentacion-*.log.' } }
        try {
            $response = Invoke-WebRequest "http://127.0.0.1:$WebPort/api/catalogo" -UseBasicParsing -TimeoutSec 2
            $probe = [Net.Sockets.TcpClient]::new('127.0.0.1',$SocketPort); $probe.Dispose()
            if ($response.StatusCode -eq 200) { $ready = $true; break }
        } catch { Start-Sleep -Milliseconds 300 }
    }
    if (!$ready) { throw 'Los servidores no estuvieron listos en 30 segundos.' }
    Write-Host "Aplicacion: $WebUrl/menu/" -ForegroundColor Green
    Write-Host "Socket: $($SocketUrl -replace '^http','ws')" -ForegroundColor Green
    Write-Host 'Enlace del salon (dos horas):'
    Task-Artisan @('trackeat:demo-link')
    Write-Host 'Ctrl+C para cerrar los procesos de esta presentacion.'
    $stopAt = if ($RunSeconds) { [DateTime]::UtcNow.AddSeconds($RunSeconds) } else { [DateTime]::MaxValue }
    while ([DateTime]::UtcNow -lt $stopAt) {
        foreach ($process in $taskProcesses) { if ($process.HasExited) { throw 'Un proceso se detuvo. Revisa backend/storage/logs.' } }
        Start-Sleep -Seconds 1
    }
} catch { Write-Host $_.Exception.Message -ForegroundColor Red; exit 1 }
finally {
    foreach ($process in $taskProcesses) { if (!$process.HasExited) { & taskkill.exe /PID $process.Id /T /F *> $null } }
    foreach ($name in $taskEnv.Keys) { [Environment]::SetEnvironmentVariable($name, $taskEnv[$name], 'Process') }
    if ($null -ne $taskOldHot) { [IO.File]::WriteAllText($taskHot, $taskOldHot) }
}
