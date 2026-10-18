param([ValidateSet('All', 'PHP', 'SQL')][string]$Suite = 'All')
$ErrorActionPreference = 'Stop'
$runtimePath = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$containerName = 'ss-remediation-regression-' + [guid]::NewGuid().ToString('N').Substring(0, 12)
$testArtifacts=Join-Path ([IO.Path]::GetTempPath()) ('ss-remediation-artifacts-'+[guid]::NewGuid().ToString('N'))
function Invoke-SqlFile([string]$path) {
    Get-Content -LiteralPath $path -Raw | docker exec -i $containerName psql -U postgres -d rosario_addons_test -v ON_ERROR_STOP=1
    if ($LASTEXITCODE -ne 0) { throw "SQL failed: $path" }
}
if ($Suite -ne 'SQL') {
    docker run --rm --network none --mount "type=bind,source=$runtimePath,target=/var/www/html,readonly" codex-ref-v1-php:local php /var/www/html/schoolsafe-remediation/tests/regression.php
    if ($LASTEXITCODE -ne 0) { throw 'PHP regression failed' }
}
if ($Suite -eq 'PHP') { return }
try {
    docker run -d --name $containerName --label schoolsafe.remediation.disposable=true --network none --tmpfs /var/lib/postgresql/data -e POSTGRES_HOST_AUTH_METHOD=trust -e POSTGRES_DB=rosario_addons_test postgres:16
    if ($LASTEXITCODE -ne 0) { throw 'Disposable PostgreSQL startup failed' }
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        docker exec $containerName pg_isready -U postgres -d rosario_addons_test | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (!$ready) { throw 'Disposable PostgreSQL not ready' }
    Invoke-SqlFile (Join-Path $PSScriptRoot 'fixture.sql')
    foreach ($migration in (Get-ChildItem -LiteralPath (Join-Path $runtimePath 'schoolsafe-remediation') -Filter '00*.sql' | Sort-Object Name)) {
        Invoke-SqlFile $migration.FullName
    }
    & (Join-Path $PSScriptRoot 'deployment.test.ps1') -Container $containerName -Artifacts $testArtifacts
    Invoke-SqlFile (Join-Path $PSScriptRoot 'regression.sql')
    docker run --rm --network "container:$containerName" --mount "type=bind,source=$runtimePath,target=/var/www/html,readonly" -e SS_REMEDIATION_DISPOSABLE=yes codex-ref-v1-php:local php /var/www/html/schoolsafe-remediation/tests/concurrency.php
    if ($LASTEXITCODE -ne 0) { throw 'Two-connection concurrency regression failed' }
} finally {
    docker rm -f $containerName
    if (Test-Path -LiteralPath $testArtifacts) {
        $resolvedArtifacts=(Resolve-Path -LiteralPath $testArtifacts).Path
        $tempRoot=[IO.Path]::GetFullPath([IO.Path]::GetTempPath())
        if (!$resolvedArtifacts.StartsWith($tempRoot,[StringComparison]::OrdinalIgnoreCase) -or [IO.Path]::GetFileName($resolvedArtifacts) -notlike 'ss-remediation-artifacts-*') { throw 'Unexpected test-artifact cleanup path' }
        Remove-Item -LiteralPath $resolvedArtifacts -Recurse -Force
    }
}
