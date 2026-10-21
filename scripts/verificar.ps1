param([switch] $PrepareDemo)
$ErrorActionPreference = 'Stop'
$taskRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskBackend = Join-Path $taskRoot 'backend'
$taskStatePath = Join-Path $taskBackend 'storage/app/private/local-postgres/connection.json'
if (!(Test-Path -LiteralPath $taskStatePath)) { throw 'Primero configura PostgreSQL con iniciar-base-datos.bat.' }
$taskState = Get-Content -LiteralPath $taskStatePath -Raw | ConvertFrom-Json
$taskDb = if ($PrepareDemo) { 'trackeat_demo_test' } else { 'trackeat_test' }
$taskOldPassword = $env:PGPASSWORD
$taskEnv = @{}
try {
    $env:PGPASSWORD = $taskState.admin_password
    $taskPgData = Join-Path (Split-Path $taskStatePath) 'data'
    $taskHbaPath = Join-Path $taskPgData 'pg_hba.conf'
    $taskHba = [IO.File]::ReadAllText($taskHbaPath)
    $taskRule = "host $taskDb trackeat 127.0.0.1/32 scram-sha-256"
    if (!$taskHba.Contains($taskRule)) {
        [IO.File]::AppendAllText($taskHbaPath, "`n$taskRule`n")
        & (Join-Path $taskState.postgres_bin 'pg_ctl.exe') -D $taskPgData reload
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo habilitar la base aislada en PostgreSQL local.' }
    }
    $taskPsql = Join-Path $taskState.postgres_bin 'psql.exe'
    $taskPgArgs = @('-h','127.0.0.1','-p',[string]$taskState.port,'-U','trackeat_admin','-d','postgres','-w','-t','-A','-v','ON_ERROR_STOP=1')
    $exists = & $taskPsql @taskPgArgs -c "SELECT 1 FROM pg_database WHERE datname='$taskDb'"
    if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL no responde. Inicia la base local.' }
    if (($exists -join '').Trim() -ne '1') {
        & (Join-Path $taskState.postgres_bin 'createdb.exe') -h 127.0.0.1 -p $taskState.port -U trackeat_admin -w -O trackeat $taskDb
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la base aislada de pruebas.' }
    }
    $values = @{DB_CONNECTION='pgsql';DB_HOST='127.0.0.1';DB_PORT=[string]$taskState.port;DB_DATABASE=$taskDb;DB_USERNAME='trackeat';DB_PASSWORD=$taskState.password;DB_URL='';BROADCAST_CONNECTION='log'}
    foreach ($name in $values.Keys) { $taskEnv[$name] = [Environment]::GetEnvironmentVariable($name,'Process'); [Environment]::SetEnvironmentVariable($name,$values[$name],'Process') }
    Push-Location $taskBackend
    try {
        $runner = Join-Path $PSScriptRoot 'php.ps1'
        & $runner artisan config:clear
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo limpiar la configuracion.' }
        if ($PrepareDemo) {
            & $runner artisan migrate --force
            if ($LASTEXITCODE -ne 0) { throw 'Fallo la migracion de demostracion.' }
            & $runner artisan db:seed
        } else { & $runner vendor/bin/phpunit --colors=never }
        if ($LASTEXITCODE -ne 0) { throw 'La verificacion termino con fallos.' }
    } finally { Pop-Location }
} finally {
    $env:PGPASSWORD = $taskOldPassword
    foreach ($name in $taskEnv.Keys) { [Environment]::SetEnvironmentVariable($name,$taskEnv[$name],'Process') }
}
