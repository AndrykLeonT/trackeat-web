# Práctica 5: TrackEat detrás de un balanceador Nginx con dos nodos.
#   Ensayo en una laptop:   scripts\balanceo.ps1 -Rol Ensayo
#   PC del balanceador:     scripts\balanceo.ps1 -Rol Balanceador -IpBalanceador 192.168.1.10 -Nodos 192.168.1.20,192.168.1.21
#   PC de cada nodo:        scripts\balanceo.ps1 -Rol Nodo -Numero 1 -IpBalanceador 192.168.1.10
#   Cambiar algoritmo:      scripts\balanceo.ps1 -Algoritmo rotatorio -Recargar
#   Apagar/prender nodo:    scripts\balanceo.ps1 -ApagarNodo 2   |   -PrenderNodo 2   (solo en ensayo)
#   Detener todo:           scripts\balanceo.ps1 -Detener
param(
    [ValidateSet('Ensayo','Balanceador','Nodo')] [string] $Rol = 'Ensayo',
    [string] $IpBalanceador = '127.0.0.1',
    [string[]] $Nodos = @(),
    [ValidateRange(1,2)] [int] $Numero = 1,
    [ValidateSet('least_conn','rotatorio','ip_hash')] [string] $Algoritmo = 'least_conn',
    [ValidateRange(1,65535)] [int] $PuertoNodo = 8000,
    [switch] $Recargar,
    [switch] $Detener,
    [ValidateRange(0,2)] [int] $ApagarNodo = 0,
    [ValidateRange(0,2)] [int] $PrenderNodo = 0
)
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$backend = Join-Path $root 'backend'
$carpeta = Join-Path $root 'balanceo'
$conf = Join-Path $carpeta 'conf'
$estadoFile = Join-Path $carpeta 'estado.json'
$secretosFile = Join-Path $carpeta 'db.json'
$envFile = Join-Path $backend '.env'
$contenedor = 'trackeat-db'
$puertoBalanceador = 8080
$puertoReverb = 8081

function Write-Ok([string] $Text) { Write-Host $Text -ForegroundColor Green }
function Quote-Argument([string] $Value) {
    return '"' + [regex]::Replace([regex]::Replace($Value, '(\\*)"', '$1$1\"'), '(\\+)$', '$1$1') + '"'
}
function New-Secret {
    $bytes = New-Object byte[] 24
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $random.GetBytes($bytes) } finally { $random.Dispose() }
    return ([BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant()
}
function Read-Json([string] $Path) {
    if (Test-Path -LiteralPath $Path) { return Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json } else { return $null }
}
function Write-Json([string] $Path, $Value) {
    [IO.File]::WriteAllText($Path, ($Value | ConvertTo-Json -Depth 5), [Text.UTF8Encoding]::new($false))
}
function Initialize-Php {
    $command = Get-Command php.exe -ErrorAction SilentlyContinue
    $script:php = if ($command) { $command.Source } elseif (Test-Path 'C:\xampp\php\php.exe') { 'C:\xampp\php\php.exe' } else {
        Get-ChildItem 'C:\laragon\bin\php\*\php.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
    }
    if (!$script:php) { throw 'Instala PHP 8.2+ y agrega php.exe al PATH.' }
    # artisan serve lanza otro php sin banderas -d; las extensiones que falten se cargan con un .ini extra.
    $iniDir = Join-Path $carpeta 'php'
    New-Item -ItemType Directory -Path $iniDir -Force | Out-Null
    $modules = (& $script:php -r 'echo json_encode(get_loaded_extensions());') | ConvertFrom-Json
    $lines = @()
    foreach ($extension in @('pdo_pgsql','bcmath','mbstring','openssl','fileinfo','curl')) {
        if ($modules -notcontains $extension) {
            if (!(Test-Path -LiteralPath (Join-Path (Split-Path $script:php) "ext/php_$extension.dll"))) { throw "Falta la extension PHP $extension. Revisa php --ini." }
            $lines += "extension=$extension"
        }
    }
    # Sin OPcache, php -S recompila Laravel en cada peticion.
    if ($modules -notcontains 'Zend OPcache' -and (Test-Path -LiteralPath (Join-Path (Split-Path $script:php) 'ext/php_opcache.dll'))) { $lines += 'zend_extension=opcache' }
    $lines += @('opcache.enable_cli=1', 'opcache.validate_timestamps=1')
    [IO.File]::WriteAllText((Join-Path $iniDir 'extensiones.ini'), ($lines -join "`n") + "`n")
    $env:PHP_INI_SCAN_DIR = $iniDir
    # Composer tambien necesita pdo_pgsql; con el .ini de arriba ya lo ve.
    if (!(Test-Path -LiteralPath (Join-Path $backend 'vendor/autoload.php')) -or !(Test-Path -LiteralPath (Join-Path $backend 'vendor/pusher'))) {
        if (!(Get-Command composer -ErrorAction SilentlyContinue)) { throw 'Instala Composer y vuelve a correr el script.' }
        Write-Host 'Instalando dependencias de PHP (composer install)...'
        Push-Location $backend
        try { & composer install --no-interaction; if ($LASTEXITCODE -ne 0) { throw 'Fallo composer install.' } } finally { Pop-Location }
    }
}
function Artisan([string[]] $Arguments) {
    Push-Location $backend
    try {
        & $script:php artisan @Arguments
        if ($LASTEXITCODE -ne 0) { throw "Fallo Laravel: artisan $($Arguments -join ' ')" }
    } finally { Pop-Location }
}
function Start-Hidden([string] $Exe, [string[]] $Arguments, [string] $Directory, [string] $Name) {
    $logs = Join-Path $carpeta 'logs'
    New-Item -ItemType Directory -Path $logs -Force | Out-Null
    # cmd hace la redireccion: asi el proceso no hereda la consola de quien llamo al script
    # y el script termina aunque su salida se capture con una tuberia.
    $command = ((@($Exe) + $Arguments) | ForEach-Object { Quote-Argument $_ }) -join ' '
    $out = Quote-Argument (Join-Path $logs "$Name.stdout.log")
    $err = Quote-Argument (Join-Path $logs "$Name.stderr.log")
    $process = Start-Process -FilePath 'cmd.exe' -ArgumentList "/d /s /c `"$command > $out 2> $err`"" -WorkingDirectory $Directory -WindowStyle Hidden -PassThru
    return $process.Id
}
function Start-Revivible([string[]] $Arguments, [string] $Name) {
    # Reverb y la cola consultan la base; si se cae, terminan. Este ciclo los vuelve a levantar.
    $command = ((@($script:php) + $Arguments) | ForEach-Object { "'" + ($_ -replace "'", "''") + "'" }) -join ' '
    return Start-Hidden 'powershell.exe' @('-NoProfile','-Command',"while (`$true) { & $command; Start-Sleep -Seconds 3 }") $backend $Name
}
function Stop-Tree([int] $ProcessId) {
    if ($ProcessId -and (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue)) { & taskkill.exe /PID $ProcessId /T /F *> $null }
}
function Wait-Url([string] $Url, [int] $Seconds) {
    $deadline = [DateTime]::UtcNow.AddSeconds($Seconds)
    while ([DateTime]::UtcNow -lt $deadline) {
        try { if ((Invoke-WebRequest $Url -UseBasicParsing -TimeoutSec 3).StatusCode -eq 200) { return $true } } catch { Start-Sleep -Milliseconds 500 }
    }
    return $false
}
function Set-EnvValues([hashtable] $Values) {
    if (!(Test-Path -LiteralPath $envFile)) { throw 'Falta backend/.env. Copia el .env del balanceador (no generes otra APP_KEY).' }
    $respaldo = "$envFile.antes-balanceo"
    if (!(Test-Path -LiteralPath $respaldo)) { Copy-Item -LiteralPath $envFile -Destination $respaldo }
    $text = [IO.File]::ReadAllText($envFile)
    foreach ($key in $Values.Keys) {
        $line = "$key=$($Values[$key])"
        $pattern = '(?m)^#?\s*' + [regex]::Escape($key) + '\s*=.*$'
        if ([regex]::IsMatch($text, $pattern)) { $text = [regex]::new($pattern).Replace($text, $line.Replace('$', '$$'), 1) }
        else { $text = $text.TrimEnd() + "`n$line`n" }
    }
    [IO.File]::WriteAllText($envFile, $text, [Text.UTF8Encoding]::new($false))
}
function Find-Nginx {
    $exe = Get-ChildItem 'C:\laragon\bin\nginx\*\nginx.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
    if (!$exe) { $command = Get-Command nginx.exe -ErrorAction SilentlyContinue; if ($command) { $exe = $command.Source } }
    if (!$exe) { throw 'No se encontro nginx.exe. Instala Laragon o descarga Nginx para Windows y agregalo al PATH.' }
    return $exe
}
function Invoke-Nginx([string[]] $Arguments) {
    $exe = Find-Nginx
    $prefix = ($carpeta -replace '\\','/') + '/'
    Push-Location $carpeta
    try { & $exe -p $prefix -c conf/nginx.conf @Arguments 2>&1 | Out-Host; return $LASTEXITCODE } finally { Pop-Location }
}
function Write-NginxConf([string[]] $Servidores, [string] $Modo) {
    $directiva = switch ($Modo) { 'least_conn' { '    least_conn;' } 'ip_hash' { '    ip_hash;' } default { '    # turno rotatorio: es el que usa Nginx sin directiva' } }
    $lineas = ($Servidores | ForEach-Object { "    server $_ max_fails=2 fail_timeout=10s;" }) -join "`n"
    $texto = @"
# Generado por scripts/balanceo.ps1 (algoritmo: $Modo). No editar a mano: usa -Algoritmo X -Recargar.
upstream trackeat {
$directiva
$lineas
}

map `$http_upgrade `$connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen $puertoBalanceador;

    # El navegador pide el icono en cada recarga y se come un turno.
    location = /favicon.ico { return 204; }

    # WebSockets de Reverb (pantalla de cocina): una sola instancia en esta PC.
    location /app {
        proxy_pass http://127.0.0.1:$puertoReverb;
        proxy_http_version 1.1;
        proxy_set_header Upgrade `$http_upgrade;
        proxy_set_header Connection `$connection_upgrade;
        proxy_set_header Host `$http_host;
        proxy_read_timeout 3600s;
    }

    location / {
        proxy_pass http://trackeat;

        proxy_set_header Host              `$http_host;   # con el puerto
        proxy_set_header X-Real-IP         `$remote_addr;
        proxy_set_header X-Forwarded-For   `$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto `$scheme;

        proxy_next_upstream error timeout http_502 http_503 http_504;

        # Para comprobar el reparto: en F12 > Red se ve que nodo atendio.
        add_header X-Nodo `$upstream_addr always;
    }
}
"@
    New-Item -ItemType Directory -Path $conf, (Join-Path $carpeta 'logs'), (Join-Path $carpeta 'temp') -Force | Out-Null
    $mime = Join-Path $conf 'mime.types'
    if (!(Test-Path -LiteralPath $mime)) { Copy-Item -LiteralPath (Join-Path (Split-Path (Find-Nginx)) 'conf/mime.types') -Destination $mime }
    [IO.File]::WriteAllText((Join-Path $conf 'balanceo.conf'), $texto, [Text.UTF8Encoding]::new($false))
}
function Start-Database {
    & docker info *> $null
    if ($LASTEXITCODE -ne 0) { throw 'Docker no responde. Abre Docker Desktop y espera a que diga "Engine running".' }
    $secretos = Read-Json $secretosFile
    if (!$secretos) {
        $secretos = [pscustomobject]@{ db_password = (New-Secret); reverb_secret = (New-Secret); sembrada = $false }
        Write-Json $secretosFile $secretos
    }
    $existe = (& docker ps -a --filter "name=^$contenedor$" --format '{{.Names}}') -eq $contenedor
    if (!$existe) {
        & docker run -d --name $contenedor -e POSTGRES_DB=trackeat -e POSTGRES_USER=trackeat -e "POSTGRES_PASSWORD=$($secretos.db_password)" -p 5432:5432 -v trackeat-db:/var/lib/postgresql/data postgres:16-alpine | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear el contenedor de PostgreSQL. Revisa que el puerto 5432 este libre.' }
    } else {
        & docker start $contenedor | Out-Null
    }
    $deadline = [DateTime]::UtcNow.AddSeconds(60)
    do {
        & docker exec $contenedor pg_isready -U trackeat -d trackeat *> $null
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep -Seconds 1
    } while ([DateTime]::UtcNow -lt $deadline)
    if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL no estuvo listo en 60 segundos. Revisa: docker logs trackeat-db' }
    return $secretos
}
function Ensure-Build {
    Remove-Item -LiteralPath (Join-Path $backend 'public/hot') -ErrorAction SilentlyContinue
    $listo = @('public/build/manifest.json','public/personal/index.html','public/menu/index.html') | ForEach-Object { Test-Path -LiteralPath (Join-Path $backend $_) }
    if ($listo -contains $false) {
        Write-Host 'Compilando el frontend (pnpm build)...'
        Push-Location (Join-Path $root 'frontend')
        try {
            if (!(Test-Path -LiteralPath 'node_modules')) { & pnpm install; if ($LASTEXITCODE -ne 0) { throw 'Fallo pnpm install.' } }
            & pnpm build; if ($LASTEXITCODE -ne 0) { throw 'Fallo pnpm build. Ejecuta pnpm install dentro de frontend.' } } finally { Pop-Location }
    }
}
function Start-Nodo([int] $N, [string] $Escucha, [int] $Puerto, [string] $Proxy) {
    $env:NODO = [string]$N
    $env:TRUSTED_PROXIES = $Proxy
    try {
        return Start-Hidden $script:php @('artisan','serve',"--host=$Escucha","--port=$Puerto",'--no-reload') $backend "nodo$N"
    } finally { Remove-Item Env:NODO, Env:TRUSTED_PROXIES -ErrorAction SilentlyContinue }
}

try {
    New-Item -ItemType Directory -Path $carpeta -Force | Out-Null
    $estado = Read-Json $estadoFile

    if ($Detener) {
        if (Test-Path -LiteralPath (Join-Path $carpeta 'logs/nginx.pid')) { Invoke-Nginx @('-s','stop') | Out-Null }
        if ($estado) { foreach ($p in $estado.procesos.PSObject.Properties) { Stop-Tree ([int]$p.Value) } }
        & docker stop $contenedor *> $null
        Remove-Item -LiteralPath $estadoFile -ErrorAction SilentlyContinue
        Write-Ok 'Balanceador, nodos, Reverb, cola y base detenidos. Los datos de la base se conservan.'
        exit 0
    }

    if ($ApagarNodo -or $PrenderNodo) {
        if (!$estado -or $estado.rol -ne 'Ensayo') { throw 'Solo en ensayo. En red, detengan el nodo con Ctrl+C en su computadora.' }
        Initialize-Php
        $n = [Math]::Max($ApagarNodo, $PrenderNodo)
        $clave = "nodo$n"
        if ($ApagarNodo) { Stop-Tree ([int]$estado.procesos.$clave); Write-Ok "Nodo $n apagado." }
        else {
            $estado.procesos.$clave = Start-Nodo $n '127.0.0.1' (8000 + $n) '127.0.0.1'
            Write-Json $estadoFile $estado
            if (!(Wait-Url "http://127.0.0.1:$(8000 + $n)/salud" 30)) { throw "El nodo $n no respondio. Revisa balanceo/logs/nodo$n.stderr.log." }
            Write-Ok "Nodo $n prendido."
        }
        exit 0
    }

    if ($Recargar) {
        if (!$estado) { throw 'El balanceador no esta corriendo. Arrancalo primero.' }
        Write-NginxConf @($estado.nodos) $Algoritmo
        if ((Invoke-Nginx @('-s','reload')) -ne 0) { throw 'Nginx no acepto la configuracion.' }
        $estado.algoritmo = $Algoritmo
        Write-Json $estadoFile $estado
        Write-Ok "Algoritmo cambiado a $Algoritmo."
        exit 0
    }

    Initialize-Php

    if ($Rol -eq 'Nodo') {
        $texto = [IO.File]::ReadAllText($envFile)
        if ($texto -notmatch '(?m)^DB_CONNECTION=pgsql' -or $texto -notmatch '(?m)^APP_KEY=base64:') { throw 'El .env no es el del balanceador. Copia backend/.env de la PC del balanceador (misma APP_KEY).' }
        Ensure-Build
        Artisan @('config:clear')
        $env:NODO = [string]$Numero
        $env:TRUSTED_PROXIES = $IpBalanceador
        Write-Ok "Nodo $Numero escuchando en 0.0.0.0:$PuertoNodo. El balanceador es $IpBalanceador. Ctrl+C para apagarlo."
        Push-Location $backend
        try { & $script:php artisan serve --host=0.0.0.0 "--port=$PuertoNodo" --no-reload } finally { Pop-Location }
        exit 0
    }

    if ($estado) { throw 'Ya hay un montaje corriendo. Ejecuta primero: scripts\balanceo.ps1 -Detener' }
    if ($Rol -eq 'Ensayo') { $IpBalanceador = '127.0.0.1'; $Nodos = @('127.0.0.1:8001','127.0.0.1:8002') }
    # Con powershell -File la lista llega como un solo texto "a,b".
    $Nodos = @($Nodos -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ })
    if ($Nodos.Count -ne 2) { throw 'Indica los dos nodos: -Nodos 192.168.1.20,192.168.1.21' }
    $Nodos = $Nodos | ForEach-Object { if ($_ -match ':\d+$') { $_ } else { "${_}:$PuertoNodo" } }

    $secretos = Start-Database
    Set-EnvValues @{
        APP_URL = "http://${IpBalanceador}:$puertoBalanceador"
        DB_CONNECTION = 'pgsql'; DB_HOST = $IpBalanceador; DB_PORT = '5432'; DB_DATABASE = 'trackeat'; DB_USERNAME = 'trackeat'; DB_PASSWORD = $secretos.db_password
        SESSION_DRIVER = 'database'; CACHE_STORE = 'database'; QUEUE_CONNECTION = 'database'; BROADCAST_CONNECTION = 'reverb'
        REVERB_APP_ID = 'trackeat'; REVERB_APP_KEY = 'trackeat-balanceo'; REVERB_APP_SECRET = $secretos.reverb_secret
        REVERB_HOST = '127.0.0.1'; REVERB_PORT = [string]$puertoReverb; REVERB_SCHEME = 'http'
        REVERB_SERVER_HOST = '127.0.0.1'; REVERB_SERVER_PORT = [string]$puertoReverb; REVERB_ALLOWED_ORIGINS = '*'
        TRACKEAT_SOCKET_URL = "http://${IpBalanceador}:$puertoBalanceador"
    }
    Artisan @('config:clear')
    Artisan @('migrate','--force')
    if (!$secretos.sembrada) { Artisan @('db:seed','--force'); $secretos.sembrada = $true; Write-Json $secretosFile $secretos }

    $procesos = [ordered]@{}
    $procesos.reverb = Start-Revivible @('artisan','reverb:start','--host=127.0.0.1',"--port=$puertoReverb") 'reverb'
    $procesos.cola = Start-Revivible @('artisan','queue:work','--sleep=1','--tries=3') 'cola'
    if ($Rol -eq 'Ensayo') {
        Ensure-Build
        $procesos.nodo1 = Start-Nodo 1 '127.0.0.1' 8001 '127.0.0.1'
        $procesos.nodo2 = Start-Nodo 2 '127.0.0.1' 8002 '127.0.0.1'
    }
    Write-NginxConf $Nodos $Algoritmo
    $nginx = Find-Nginx
    $procesos.nginx = Start-Hidden $nginx @('-p', (($carpeta -replace '\\','/') + '/'), '-c', 'conf/nginx.conf') $carpeta 'nginx'
    Write-Json $estadoFile ([ordered]@{ rol = $Rol; ip = $IpBalanceador; nodos = $Nodos; algoritmo = $Algoritmo; procesos = $procesos })

    if ($Rol -eq 'Ensayo') {
        foreach ($n in 1,2) { if (!(Wait-Url "http://127.0.0.1:800$n/salud" 40)) { throw "El nodo $n no respondio. Revisa balanceo/logs/nodo$n.stderr.log." } }
        if (!(Wait-Url "http://127.0.0.1:$puertoBalanceador/salud" 20)) { throw 'El balanceador no respondio. Revisa balanceo/logs/error.log.' }
        Write-Ok "Ensayo listo: http://127.0.0.1:$puertoBalanceador/  (nodos 8001 y 8002, algoritmo $Algoritmo)"
    } else {
        Write-Ok "Balanceador listo en http://${IpBalanceador}:$puertoBalanceador/ (algoritmo $Algoritmo)."
        Write-Host ''
        Write-Host 'Ahora en cada nodo (con el mismo commit y este mismo backend/.env copiado):'
        Write-Host "  PC nodo 1:  powershell -ExecutionPolicy Bypass -File scripts\balanceo.ps1 -Rol Nodo -Numero 1 -IpBalanceador $IpBalanceador"
        Write-Host "  PC nodo 2:  powershell -ExecutionPolicy Bypass -File scripts\balanceo.ps1 -Rol Nodo -Numero 2 -IpBalanceador $IpBalanceador"
    }
} catch { Write-Host $_.Exception.Message -ForegroundColor Red; exit 1 }
