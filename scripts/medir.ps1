# Mediciones de la Práctica 5 con Apache Bench (ab).
#   scripts\medir.ps1 -Escenario "Un nodo solo" -Url http://127.0.0.1:8001/
#   scripts\medir.ps1 -Escenario "Balanceador least_conn" -Url http://127.0.0.1:8080/
#   scripts\medir.ps1 -Escenario "Nodo 2 apagado a la mitad" -ApagarNodoA 3     (solo en ensayo)
# Cada corrida agrega una fila a balanceo/mediciones.md.
param(
    [Parameter(Mandatory)] [string] $Escenario,
    [string] $Url = 'http://127.0.0.1:8080/',
    [ValidateRange(1,100000)] [int] $Peticiones = 1000,
    [ValidateRange(1,1000)] [int] $Concurrencia = 20,
    [ValidateRange(0,600)] [int] $ApagarNodoA = 0
)
$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$tabla = Join-Path $root 'balanceo/mediciones.md'

try {
    if ($Url -match '//localhost') { throw 'Usa 127.0.0.1 o la IP, no localhost: ab intenta IPv6 primero y los numeros salen mal.' }
    if ($Url -notmatch '^http://[^/]+/') { throw 'La URL debe terminar en diagonal, por ejemplo http://127.0.0.1:8080/' }
    $ab = Get-Command ab.exe -ErrorAction SilentlyContinue
    $ab = if ($ab) { $ab.Source } else { Get-ChildItem 'C:\laragon\bin\apache\*\bin\ab.exe' -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName }
    if (!$ab) { throw 'No se encontro ab.exe. Viene con Laragon (bin\apache\...\bin) o con Apache para Windows.' }

    $apagado = $null
    if ($ApagarNodoA) {
        # Apaga el nodo 2 a mitad de la corrida para ver cuanto aguanta el balanceador.
        $script = Join-Path $PSScriptRoot 'balanceo.ps1'
        $apagado = Start-Job -ScriptBlock { param($s, $t) Start-Sleep -Seconds $t; & powershell -NoProfile -ExecutionPolicy Bypass -File $s -ApagarNodo 2 } -ArgumentList $script, $ApagarNodoA
    }
    Write-Host "Corriendo: ab -n $Peticiones -c $Concurrencia -l $Url"
    # ab escribe su avance en stderr; no es un error.
    $ErrorActionPreference = 'Continue'
    $salida = & $ab -n $Peticiones -c $Concurrencia -l $Url 2>&1 | ForEach-Object { "$_" } | Out-String
    $ErrorActionPreference = 'Stop'
    if ($apagado) { $apagado | Wait-Job | Receive-Job | Out-Host; Remove-Job $apagado }
    if ($salida -notmatch 'Requests per second:\s+([\d.]+)') { Write-Host $salida; throw 'ab no termino la prueba. Revisa que la URL responda.' }

    $rps = [double]$Matches[1]
    $fallidas = if ($salida -match 'Failed requests:\s+(\d+)') { [int]$Matches[1] } else { 0 }
    $no2xx = if ($salida -match 'Non-2xx responses:\s+(\d+)') { [int]$Matches[1] } else { 0 }
    $p = @{}
    foreach ($q in 50, 90, 99) { if ($salida -match "(?m)^\s+$q%\s+(\d+)") { $p[$q] = $Matches[1] } else { $p[$q] = '?' } }

    if (!(Test-Path -LiteralPath $tabla)) {
        [IO.File]::WriteAllText($tabla, "# Mediciones con Apache Bench`n`n| Escenario | URL | -n / -c | Peticiones/s | p50 (ms) | p90 (ms) | p99 (ms) | Fallidas | No 2xx |`n|---|---|---|---|---|---|---|---|---|`n", [Text.UTF8Encoding]::new($false))
    }
    $fila = "| $Escenario | $Url | $Peticiones / $Concurrencia | $('{0:N2}' -f $rps) | $($p[50]) | $($p[90]) | $($p[99]) | $fallidas | $no2xx |"
    [IO.File]::AppendAllText($tabla, "$fila`n", [Text.UTF8Encoding]::new($false))
    Write-Host $fila -ForegroundColor Green
    Write-Host "Agregado a balanceo/mediciones.md"
} catch { Write-Host $_.Exception.Message -ForegroundColor Red; exit 1 }
