param(
    [string] $InputPath = (Join-Path $PSScriptRoot '../output/seguridad/TrackEat_propuesta_seguridad.docx'),
    [string] $OutputPath = (Join-Path $PSScriptRoot '../output/seguridad/TrackEat_propuesta_seguridad.pdf')
)
$ErrorActionPreference = 'Stop'
$taskInput = [IO.Path]::GetFullPath($InputPath)
$taskOutput = [IO.Path]::GetFullPath($OutputPath)
$taskVbs = Join-Path ([IO.Path]::GetTempPath()) ("trackeat-word-" + [Guid]::NewGuid().ToString('N') + ".vbs")
try {
    @'
Option Explicit
Dim word, doc, hadDocs, pages
Set word = CreateObject("Word.Application")
hadDocs = (word.Documents.Count > 0)
If Not hadDocs Then word.Visible = False
word.DisplayAlerts = 0
Set doc = word.Documents.Open(WScript.Arguments(0), False, True, False)
doc.Repaginate
pages = doc.ComputeStatistics(2)
doc.ExportAsFixedFormat WScript.Arguments(1), 17
doc.Close 0
If Not hadDocs And word.Documents.Count = 0 Then word.Quit
WScript.Echo "PDF exportado por Microsoft Word. Paginas: " & pages
'@ | Set-Content -LiteralPath $taskVbs -Encoding ASCII
    & cscript.exe //Nologo $taskVbs $taskInput $taskOutput
    if ($LASTEXITCODE -ne 0) { throw "Microsoft Word devolvio codigo $LASTEXITCODE al exportar el PDF." }
} finally {
    if (Test-Path -LiteralPath $taskVbs) { Remove-Item -LiteralPath $taskVbs -Force -ErrorAction SilentlyContinue }
}
