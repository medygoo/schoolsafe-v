param([Parameter(Mandatory)][string]$Container,[Parameter(Mandatory)][string]$Artifacts)
$ErrorActionPreference='Stop'
$deploy=Join-Path $PSScriptRoot '../deploy-isolated.ps1'
[IO.Directory]::CreateDirectory($Artifacts) | Out-Null
$manifest=Join-Path $Artifacts 'review.json'
$backups=Join-Path $Artifacts 'backups'
function Check([bool]$ok,[string]$label) { if (!$ok) { throw "FAIL: $label" }; Write-Output "PASS: $label" }
foreach ($migration in @('010-payment-source-integrity.sql','011-type-refresh.sql','012-payment-reconfirmation.sql','013-parent-snapshot-guard.sql')) {
    $result=Get-Content -LiteralPath (Join-Path $PSScriptRoot "../$migration") -Raw | docker exec -i $Container psql -X -U postgres -d postgres -v ON_ERROR_STOP=1 2>&1
    Check ($LASTEXITCODE -ne 0 -and ($result -join "`n") -match 'REMEDIATION_ISOLATED_DATABASE_REQUIRED') "wrong database refused by $migration before DDL"
}
& $deploy -Mode Review -Container $Container -ManifestPath $manifest
$ledger='SELECT to_regclass(''schoolsafe_remediation_migrations'') IS NULL' | docker exec -i $Container psql -X -A -t -U postgres -d rosario_addons_test
Check ($ledger.Trim() -eq 't') 'review performs no ledger/schema writes'
$approved=Get-Content -LiteralPath $manifest -Raw | ConvertFrom-Json
Check (@($approved.pending).Count -eq 4) 'review lists exactly four guarded pending migrations'
try {
    & $deploy -Mode Apply -Container $Container -ManifestPath $manifest -BackupDirectory $backups
    throw 'Missing maintenance gate accepted'
} catch { Check ($_.Exception.Message -match 'confirm maintenance') 'apply without maintenance confirmation refused' }
$tampered=Join-Path $Artifacts 'tampered.json'
$approved.target.migrations[0].sha256='0'*64
$approved | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath $tampered -Encoding utf8
try {
    & $deploy -Mode Apply -Container $Container -ManifestPath $tampered -BackupDirectory $backups -MaintenanceConfirmed
    throw 'Changed manifest accepted'
} catch { Check ($_.Exception.Message -match 'changed; review again') 'changed reviewed checksum refused before backup/DDL' }
try {
    & $deploy -Mode Apply -Container $Container -ManifestPath $manifest -BackupDirectory (Join-Path $PSScriptRoot 'backup') -MaintenanceConfirmed
    throw 'Web-root backup accepted'
} catch { Check ($_.Exception.Message -match 'not be served') 'backup under served runtime refused' }
& $deploy -Mode Apply -Container $Container -ManifestPath $manifest -BackupDirectory $backups -MaintenanceConfirmed
$dump=@(Get-ChildItem -LiteralPath $backups -Filter '*.dump')
Check ($dump.Count -eq 1 -and $dump[0].Length -gt 0) 'one verified binary backup precedes DDL'
$backupMeta=Get-Content -LiteralPath ($dump[0].FullName+'.json') -Raw | ConvertFrom-Json
Check ($backupMeta.sha256 -eq (Get-FileHash -LiteralPath $dump[0].FullName -Algorithm SHA256).Hash.ToLowerInvariant()) 'backup SHA256 matches sidecar'
docker exec $Container createdb -U postgres remediation_restore_check
if ($LASTEXITCODE -ne 0) { throw 'Restore-test database creation failed' }
docker cp $dump[0].FullName "${Container}:/tmp/ss-remediation-restore-check.dump"
if ($LASTEXITCODE -ne 0) { throw 'Restore-test archive copy failed' }
docker exec $Container pg_restore -U postgres --no-owner --no-acl -d remediation_restore_check /tmp/ss-remediation-restore-check.dump
Check ($LASTEXITCODE -eq 0) 'pre-migration full backup restores on disposable PostgreSQL without grants'
$prior='SELECT to_regclass(''schoolsafe_remediation_migrations'') IS NULL AND to_regprocedure(''schoolsafe_remediation_fee_changed()'') IS NULL' | docker exec -i $Container psql -X -A -t -U postgres -d remediation_restore_check
Check ($prior.Trim() -eq 't') 'restored backup contains the state before guarded migrations'
$count='SELECT count(*) FROM schoolsafe_remediation_migrations' | docker exec -i $Container psql -X -A -t -U postgres -d rosario_addons_test
Check ($count.Trim() -eq '4') 'each migration has committed matching ledger entry'
& $deploy -Mode Review -Container $Container -ManifestPath $manifest
$replay=@(& $deploy -Mode Apply -Container $Container -ManifestPath $manifest)
Check (($replay -join "`n") -match 'ALREADY_APPLIED') 'reviewed replay skips identical migrations without writes'
