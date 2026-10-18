$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$manifestPath = Join-Path $PSScriptRoot 'FINAL_VISUAL_MANIFEST.json'
$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
if ($manifest.statuses.visualCatalogue -ne 'READY_FOR_OWNER_REVIEW') {
    throw 'The verified final manifest is required.'
}

# Verify the exact application and evidence files before including them.
foreach ($item in @($manifest.appFiles) + @($manifest.documentation) + @($manifest.tests)) {
    $full = [IO.Path]::GetFullPath((Join-Path $root $item.file))
    if (-not $full.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw "File outside workspace: $full"
    }
    if ((Get-FileHash -LiteralPath $full -Algorithm SHA256).Hash.ToLowerInvariant() -ne $item.sha256) {
        throw "File changed after final verification: $($item.file)"
    }
}

$paths = [Collections.Generic.List[string]]::new()
foreach ($item in $manifest.appFiles) { $paths.Add((Join-Path $root $item.file)) }
foreach ($file in Get-ChildItem -LiteralPath $PSScriptRoot -File) {
    if ($file.Extension -in '.md', '.json', '.csv', '.cjs', '.ps1' -and $file.Name -ne 'FINAL_VISUAL_PACKAGE.json') {
        $paths.Add($file.FullName)
    }
}
foreach ($name in 'feature-tests', 'school-workflows-tests', 'record-tests', 'lot2-tests', 'pro-max-tests', 'overview-composition-tests') {
    foreach ($file in Get-ChildItem -LiteralPath (Join-Path $PSScriptRoot $name) -File) {
        if ($file.Extension -in '.json', '.png', '.md') { $paths.Add($file.FullName) }
    }
}
$paths.Add((Join-Path $PSScriptRoot 'SCHOOLSAFE_NON_RACCORDEMENT_CURSOR_V1.zip'))
$archivePath = Join-Path $PSScriptRoot 'SCHOOLSAFE_VISUAL_V1_CURSOR_PACKAGE.zip'
if (Test-Path -LiteralPath $archivePath) { throw 'Archive already exists. Preserve it and choose a new version name.' }
$archive = [IO.Compression.ZipFile]::Open($archivePath, [IO.Compression.ZipArchiveMode]::Create)
$inventory = @()
try {
    foreach ($file in $paths | Sort-Object -Unique) {
        $relative = $file.Substring($root.Length + 1).Replace('\', '/')
        [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file, $relative, [IO.Compression.CompressionLevel]::Optimal)
        $inventory += [ordered]@{ file = $relative; sha256 = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLowerInvariant() }
    }
} finally { $archive.Dispose() }

$check = [IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    if ($check.Entries.Count -ne $inventory.Count) { throw 'Archive entry count mismatch.' }
    $expected = @{}
    foreach ($item in $inventory) { $expected[$item.file] = $item.sha256 }
    foreach ($item in $check.Entries) {
        $stream = $item.Open()
        $sha = [Security.Cryptography.SHA256]::Create()
        try { $actual = [BitConverter]::ToString($sha.ComputeHash($stream)).Replace('-', '').ToLowerInvariant() }
        finally { $stream.Dispose(); $sha.Dispose() }
        if ($expected[$item.FullName] -ne $actual) { throw "Archive hash mismatch: $($item.FullName)" }
    }
} finally { $check.Dispose() }

$receipt = [ordered]@{
    date = [DateTime]::UtcNow.ToString('o')
    file = 'docs/codex-ui-v1/SCHOOLSAFE_VISUAL_V1_CURSOR_PACKAGE.zip'
    sha256 = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()
    bytes = (Get-Item -LiteralPath $archivePath).Length
    entries = $inventory.Count
    entriesVerified = $true
    scope = 'CODEX_VISUAL_FILES_AND_EVIDENCE_ONLY'
    integrationPerformed = $false
    files = $inventory
}
$receipt | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'FINAL_VISUAL_PACKAGE.json') -Encoding UTF8
[pscustomobject]$receipt | Select-Object date, file, sha256, bytes, entries, entriesVerified, integrationPerformed | ConvertTo-Json
