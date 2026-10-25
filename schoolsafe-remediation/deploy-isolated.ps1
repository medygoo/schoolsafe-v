param(
    [ValidateSet('Review', 'Apply')][string]$Mode = 'Review',
    [Parameter(Mandatory)][ValidatePattern('^[a-zA-Z0-9][a-zA-Z0-9_.-]*$')][string]$Container,
    [Parameter(Mandatory)][string]$ManifestPath,
    [ValidatePattern('^[a-zA-Z_][a-zA-Z0-9_]*$')][string]$DbUser = 'postgres',
    [string]$BackupDirectory,
    [switch]$MaintenanceConfirmed
)
$ErrorActionPreference = 'Stop'
$databaseName = 'rosario_addons_test'
$contextHost = docker context inspect --format '{{.Endpoints.docker.Host}}'
if ($LASTEXITCODE -ne 0 -or $contextHost -notmatch '^(npipe://|unix://|tcp://(127\.0\.0\.1|localhost):)') { throw 'Local Docker context required' }
$info = (docker inspect $Container | ConvertFrom-Json)[0]
if ($LASTEXITCODE -ne 0 -or !$info.State.Running -or $info.Config.Image -notmatch '^postgres:') { throw 'Running local PostgreSQL container required' }
$disposable = $Container.StartsWith('ss-remediation-regression-') -and $info.Config.Labels.'schoolsafe.remediation.disposable' -eq 'true' -and $info.HostConfig.NetworkMode -eq 'none'
if ($Container -ne 'codex-addons-db' -and !$disposable) { throw 'Container is outside the isolated allowlist' }
function Query([string]$sql) {
    $result = $sql | docker exec -e 'PGOPTIONS=-c lock_timeout=5s -c statement_timeout=30s' -i $Container psql -X -A -t -U $DbUser -d $databaseName -v ON_ERROR_STOP=1
    if ($LASTEXITCODE -ne 0) { throw 'Isolated PostgreSQL query failed' }
    return (($result | ForEach-Object { [string]$_ }) -join "`n").Trim()
}
$identity = Query "SELECT json_build_object('database',current_database(),'oid',(SELECT oid FROM pg_database WHERE datname=current_database()),'systemId',(SELECT system_identifier::text FROM pg_control_system()),'version',current_setting('server_version_num')::integer)"
$dbIdentity = $identity | ConvertFrom-Json
if ($dbIdentity.database -ne $databaseName -or $dbIdentity.version -lt 150000) { throw 'Isolated PostgreSQL 15+ required' }
$baseline = Query "SELECT (to_regclass('schoolsafe_remediation_payment_purpose') IS NOT NULL AND to_regclass('schoolsafe_remediation_receipt_unique') IS NOT NULL AND to_regprocedure('schoolsafe_remediation_refresh(integer,integer)') IS NOT NULL)::text"
if ($baseline -ne 'true') { throw 'Migrations 001 through 009 must already be installed' }
$duplicates = Query "SELECT count(*) FROM (SELECT event_id FROM schoolsafe_remediation_notifications WHERE audience='principal_parent' GROUP BY event_id HAVING count(*)>1) conflicts"
if ($duplicates -ne '0') { throw 'Duplicate historical parent snapshots require review; no rows will be removed' }
$ledgerExists = (Query "SELECT (to_regclass('schoolsafe_remediation_migrations') IS NOT NULL)::text") -eq 'true'
$installed = @{}
if ($ledgerExists) {
    $ledgerRows = Query 'SELECT name,sha256 FROM schoolsafe_remediation_migrations ORDER BY name'
    if ($ledgerRows) { foreach ($line in ($ledgerRows -split "`n")) { $parts=$line -split '\|'; $installed[$parts[0]]=$parts[1] } }
}
$files = @('010-payment-source-integrity.sql','011-type-refresh.sql','012-payment-reconfirmation.sql','013-parent-snapshot-guard.sql')
$migrations = @()
foreach ($name in $files) {
    $path = Join-Path $PSScriptRoot $name
    $hash = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($installed.ContainsKey($name) -and $installed[$name] -ne $hash) { throw "Installed checksum differs: $name" }
    $migrations += [pscustomobject]@{ name=$name; sha256=$hash; applied=$installed.ContainsKey($name) }
}
# Never stamp previously executed, untracked DDL as if this runner had verified it.
$markers = @('schoolsafe_remediation_fee_changed()','schoolsafe_remediation_category_changed()','schoolsafe_remediation_payment_version()','schoolsafe_remediation_parent_snapshot_guard()')
for ($index=0; $index -lt $files.Count; $index++) {
    $markerExists=(Query "SELECT (to_regprocedure('$($markers[$index])') IS NOT NULL)::text") -eq 'true'
    if ($markerExists -ne $migrations[$index].applied) { throw "Schema/ledger mismatch: $($files[$index]); inspect untracked or incomplete deployment" }
}
$pending=@($migrations | Where-Object { !$_.applied })
$fingerprint=[pscustomobject]@{ containerId=$info.Id; database=$dbIdentity; migrations=$migrations }
if ($Mode -eq 'Review') {
    $manifest=[pscustomobject]@{ format=1; reviewedAt=[DateTime]::UtcNow.ToString('o'); target=$fingerprint; pending=@($pending.name); maintenanceRequired=$true }
    $manifestFullPath=[IO.Path]::GetFullPath($ManifestPath)
    [IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($manifestFullPath)) | Out-Null
    $manifest | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $manifestFullPath -Encoding utf8
    Write-Output "REVIEW_ONLY: $($pending.Count) pending migrations; database unchanged. Manifest: $manifestFullPath"
    return
}
$approved=Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
$reviewAge=[DateTime]::UtcNow-[DateTime]::Parse($approved.reviewedAt).ToUniversalTime()
if ($approved.format -ne 1 -or $reviewAge.TotalHours -gt 24 -or $reviewAge.TotalMinutes -lt -5) { throw 'Fresh reviewed manifest required' }
if (($approved.target | ConvertTo-Json -Depth 8 -Compress) -ne ($fingerprint | ConvertTo-Json -Depth 8 -Compress)) { throw 'Review target, schema state or migration hashes changed; review again' }
if (!$pending.Count) { Write-Output 'ALREADY_APPLIED: identical checksums; no DDL or data writes'; return }
if (!$MaintenanceConfirmed) { throw 'Main must pause all writers and confirm maintenance before applying' }
if (!$BackupDirectory) { throw 'Backup directory outside the web runtime required' }
$backupRoot=[IO.Path]::GetFullPath($BackupDirectory)
$runtimeRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if ($backupRoot.TrimEnd([char[]]'\/') -eq $runtimeRoot.TrimEnd([char[]]'\/') -or $backupRoot.StartsWith($runtimeRoot.TrimEnd([char[]]'\/')+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) { throw 'Backups must not be served by the web runtime' }
[IO.Directory]::CreateDirectory($backupRoot) | Out-Null
$backupId=[guid]::NewGuid().ToString('N')
$remoteDump="/tmp/ss-remediation-$backupId.dump"
$localDump=Join-Path $backupRoot "rosario_addons_test-$backupId.dump"
try {
    docker exec $Container pg_dump -U $DbUser -d $databaseName --lock-wait-timeout=5s -Fc -f $remoteDump
    if ($LASTEXITCODE -ne 0) { throw 'Backup failed; nothing applied' }
    docker exec $Container pg_restore --list $remoteDump | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Backup archive invalid; nothing applied' }
    docker cp "${Container}:$remoteDump" $localDump
    if ($LASTEXITCODE -ne 0 -or (Get-Item -LiteralPath $localDump).Length -eq 0) { throw 'Backup copy failed; nothing applied' }
    $backupHash=(Get-FileHash -LiteralPath $localDump -Algorithm SHA256).Hash.ToLowerInvariant()
    [pscustomobject]@{ path=$localDump; sha256=$backupHash; target=$fingerprint; createdAt=[DateTime]::UtcNow.ToString('o') } | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath "$localDump.json" -Encoding utf8
    Write-Output "BACKUP_VERIFIED: $localDump SHA256=$backupHash"
} finally {
    docker exec $Container rm -f -- $remoteDump | Out-Null
}
foreach ($migration in $pending) {
    $path=Join-Path $PSScriptRoot $migration.name
    if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant() -ne $migration.sha256) { throw 'Migration changed after review' }
    $body=Get-Content -LiteralPath $path -Raw
    if ($body -notmatch '\ABEGIN;\s' -or $body -notmatch 'COMMIT;\s*\z') { throw 'Expected transactional migration envelope' }
    $body=[regex]::Replace($body,'\ABEGIN;\s*','')
    $body=[regex]::Replace($body,'COMMIT;\s*\z','')
    $sql=@"
BEGIN;
SET LOCAL lock_timeout='5s';
SET LOCAL statement_timeout='30s';
SELECT pg_advisory_xact_lock(734122,100);
DO `$guard`$ BEGIN IF current_database()<>'rosario_addons_test' THEN RAISE EXCEPTION 'REMEDIATION_ISOLATED_DATABASE_REQUIRED'; END IF; END `$guard`$;
CREATE TABLE IF NOT EXISTS schoolsafe_remediation_migrations(name text PRIMARY KEY,sha256 text NOT NULL CHECK(sha256 ~ '^[a-f0-9]{64}$'),applied_at timestamptz NOT NULL DEFAULT clock_timestamp());
DO `$guard`$ BEGIN IF EXISTS(SELECT 1 FROM schoolsafe_remediation_migrations WHERE name='$($migration.name)') THEN RAISE EXCEPTION 'MIGRATION_ALREADY_APPLIED_REVIEW_AGAIN'; END IF; END `$guard`$;
$body
INSERT INTO schoolsafe_remediation_migrations(name,sha256) VALUES('$($migration.name)','$($migration.sha256)');
COMMIT;
"@
    $sql | docker exec -i $Container psql -X -U $DbUser -d $databaseName -v ON_ERROR_STOP=1
    if ($LASTEXITCODE -ne 0) { throw "Migration rolled back: $($migration.name). Earlier successful migrations stay installed; review again before retrying." }
    Write-Output "APPLIED: $($migration.name)"
}
Write-Output 'APPLY_COMPLETE: isolated schema migrations only; no permissions granted or funds transferred'
