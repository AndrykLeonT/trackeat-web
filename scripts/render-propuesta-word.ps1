param(
    [string] $InputPath = (Join-Path $PSScriptRoot '../output/seguridad/TrackEat_propuesta_seguridad.docx'),
    [string] $OutputPath = (Join-Path $PSScriptRoot '../output/seguridad/TrackEat_propuesta_seguridad.pdf')
)
$ErrorActionPreference = 'Stop'
$taskInput = [IO.Path]::GetFullPath($InputPath)
$taskOutput = [IO.Path]::GetFullPath($OutputPath)
$taskWord = $null
$taskDocument = $null
$taskHadDocuments = $true
try {
    $taskWord = New-Object -ComObject Word.Application
    $taskHadDocuments = $taskWord.Documents.Count -gt 0
    if (!$taskHadDocuments) { $taskWord.Visible = $false }
    $taskWord.DisplayAlerts = 0
    $taskDocument = $taskWord.Documents.Open($taskInput, $false, $true, $false)
    $taskDocument.Repaginate()
    $taskPages = $taskDocument.ComputeStatistics(2)
    $taskDocument.ExportAsFixedFormat($taskOutput, 17)
    Write-Output "PDF exportado por Microsoft Word. Paginas: $taskPages"
} finally {
    if ($taskDocument) { $taskDocument.Close(0); [void][Runtime.InteropServices.Marshal]::ReleaseComObject($taskDocument) }
    if ($taskWord) {
        if (!$taskHadDocuments -and $taskWord.Documents.Count -eq 0) { $taskWord.Quit() }
        [void][Runtime.InteropServices.Marshal]::ReleaseComObject($taskWord)
    }
}
