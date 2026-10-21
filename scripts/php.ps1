param([Parameter(ValueFromRemainingArguments = $true)] [string[]] $PhpArguments)
$ErrorActionPreference = 'Stop'
$taskPhp = (Get-Command php.exe -ErrorAction Stop).Source
$taskPhpDir = Split-Path $taskPhp
$taskFlags = @('-d', "extension_dir=$taskPhpDir/ext")
$taskModules = (& $taskPhp -r 'echo json_encode(get_loaded_extensions());') | ConvertFrom-Json
foreach ($extension in @('pdo_pgsql','bcmath','mbstring','openssl','fileinfo','curl','zip')) {
    if ($taskModules -notcontains $extension) { $taskFlags += @('-d', "extension=$extension") }
}
& $taskPhp @taskFlags @PhpArguments
exit $LASTEXITCODE
