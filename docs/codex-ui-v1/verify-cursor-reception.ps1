$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$package = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'FINAL_VISUAL_PACKAGE.json') -Raw | ConvertFrom-Json
$manifest = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'FINAL_VISUAL_MANIFEST.json') -Raw | ConvertFrom-Json
$issues = [Collections.Generic.List[string]]::new()
function HashFile([string]$path) {
    (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
}
function WorkspaceFile([string]$relative) {
    $full = [IO.Path]::GetFullPath((Join-Path $root $relative))
    if (-not $full.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Unexpected file outside workspace: $relative"
    }
    $full
}
$zipPath = WorkspaceFile $package.file
$zipHash = HashFile $zipPath
if ($zipHash -ne $package.sha256) { $issues.Add('ZIP checksum mismatch') }
if ((Get-Item -LiteralPath $zipPath).Length -ne $package.bytes) { $issues.Add('ZIP size mismatch') }
$expected = @{}
foreach ($item in $package.files) {
    if ($expected.ContainsKey($item.file)) { throw "Duplicate package entry: $($item.file)" }
    $expected[$item.file] = $item.sha256
    $full = WorkspaceFile $item.file
    if (-not (Test-Path -LiteralPath $full -PathType Leaf)) { $issues.Add("Missing delivered file: $($item.file)") }
    elseif ((HashFile $full) -ne $item.sha256) { $issues.Add("Delivered file changed: $($item.file)") }
}
$zip = [IO.Compression.ZipFile]::OpenRead($zipPath)
$entryCount = $zip.Entries.Count
$seen = @{}
try {
    if ($entryCount -ne $package.entries -or $entryCount -ne $expected.Count) { $issues.Add('ZIP entry count mismatch') }
    foreach ($entry in $zip.Entries) {
        if ($seen.ContainsKey($entry.FullName)) { $issues.Add("Duplicate ZIP entry: $($entry.FullName)") }
        $seen[$entry.FullName] = $true
        $stream = $entry.Open()
        $sha = [Security.Cryptography.SHA256]::Create()
        try { $actual = [BitConverter]::ToString($sha.ComputeHash($stream)).Replace('-', '').ToLowerInvariant() }
        finally { $stream.Dispose(); $sha.Dispose() }
        if ($expected[$entry.FullName] -ne $actual) { $issues.Add("ZIP entry changed: $($entry.FullName)") }
    }
} finally { $zip.Dispose() }

$checks = @()
foreach ($control in $manifest.sourceControls) {
    $path = [IO.Path]::GetFullPath((Join-Path $root $control.file))
    if ((HashFile $path) -ne $control.sha256) { $issues.Add("Snapshot control changed: $($control.file)") }
}
$snapshotEntries = @(Import-Csv -LiteralPath (Join-Path $root '../TREE.sha256') -Delimiter "`t" -Header File,Sha256,Bytes)
foreach ($base in @($root, [IO.Path]::GetFullPath((Join-Path $root '../baseline')))) {
    $checked = 0
    foreach ($item in $snapshotEntries) {
        if (-not $item.File -or $item.Sha256 -notmatch '^[0-9a-fA-F]{64}$' -or $item.Bytes -notmatch '^\d+$') { throw 'Malformed snapshot control row' }
        $full = [IO.Path]::GetFullPath((Join-Path $base $item.File))
        if (-not $full.StartsWith($base + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Snapshot path escapes base' }
        if (-not (Test-Path -LiteralPath $full -PathType Leaf)) { $issues.Add("Snapshot file missing: $full"); continue }
        if ((HashFile $full) -ne $item.Sha256.ToLowerInvariant()) { $issues.Add("Snapshot file changed: $full") }
        if ((Get-Item -LiteralPath $full).Length -ne [long]$item.Bytes) { $issues.Add("Snapshot size changed: $full") }
        $checked++
    }
    $checks += [ordered]@{ path = $base; filesChecked = $checked }
}
$testReports = @()
foreach ($item in $manifest.tests) {
    $data = Get-Content -LiteralPath (WorkspaceFile $item.file) -Raw | ConvertFrom-Json
    $passed = $data.status -eq 'PASS' -or ($data.results.Count -gt 0 -and @($data.results | Where-Object status -ne 'PASS').Count -eq 0)
    if (-not $passed -or $data.errors.Count -or $data.apiRequests.Count) { $issues.Add("Test report not passing: $($item.file)") }
    $testReports += [ordered]@{ file = $item.file; executionDate = $data.date; previouslyExecutedStatus = if ($passed) { 'PASS' } else { 'FAIL' } }
}
$notes = @()
foreach ($name in 'CURSOR_RECEPTION_VISUELLE_V1.md', 'verify-cursor-reception.ps1') {
    $notes += [ordered]@{ file = $name; sha256 = HashFile (Join-Path $PSScriptRoot $name) }
}
$result = [ordered]@{
    reference = 'CODEX_TO_CURSOR_VISUAL_HANDOFF_V1'
    verifiedAt = [DateTime]::UtcNow.ToString('o')
    status = if ($issues.Count -eq 0) { 'PASS' } else { 'FAIL' }
    package = [ordered]@{ file = $package.file; sha256 = $zipHash; bytes = $package.bytes; entriesChecked = $entryCount }
    deliveredFilesChecked = $expected.Count
    sourceIntegrity = $checks
    testsRerunDuringHandoff = $false
    testReports = $testReports
    handoffDocuments = $notes
    issues = @($issues.ToArray())
    directMessageSent = $false
    cursorReceiptConfirmed = $false
    integrationPerformed = $false
}
$result | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'CURSOR_RECEPTION_VERIFICATION.json') -Encoding UTF8
[pscustomobject]$result | Select-Object reference,verifiedAt,status,package,deliveredFilesChecked,sourceIntegrity,testsRerunDuringHandoff,directMessageSent,cursorReceiptConfirmed,integrationPerformed,@{Name='issueCount';Expression={$issues.Count}} | ConvertTo-Json -Depth 5
if ($issues.Count) { exit 1 }
