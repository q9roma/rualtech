param(
    [string]$Python = 'C:\Users\romam\.cache\codex-runtimes\codex-primary-runtime\dependencies\python\python.exe',
    [string]$Key = "$env:USERPROFILE\.ssh\rualtech_actions_staging_2026"
)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
& $Python scripts/package-staging-source.py
if ($LASTEXITCODE -ne 0) { throw 'Source packaging failed' }
$manifest = Get-Content dist/staging-source.json -Raw | ConvertFrom-Json
$snapshot = $manifest.snapshot
$checksum = $manifest.sha256
if ($snapshot -notmatch '^[a-f0-9]{40}$' -or $checksum -notmatch '^[a-f0-9]{64}$') { throw 'Invalid source identity' }
$destination = 'altech-staging@130.49.149.233'
$sshOptions = @('-i', $Key, '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes')
$remote = "/srv/altech/staging/incoming/$snapshot"
& ssh @sshOptions $destination "mkdir -p '$remote'"
if ($LASTEXITCODE -ne 0) { throw 'Remote preparation failed' }
& scp @sshOptions dist/staging-source.tar.gz "${destination}:$remote/source.tar.gz"
if ($LASTEXITCODE -ne 0) { throw 'Source upload failed' }
& scp @sshOptions deploy/build-staging.sh "${destination}:$remote/build-staging.sh"
if ($LASTEXITCODE -ne 0) { throw 'Build script upload failed' }
& ssh @sshOptions $destination "nice -n 10 bash '$remote/build-staging.sh' '$snapshot' '$checksum'"
if ($LASTEXITCODE -ne 0) { throw 'Staging build or tests failed' }
& ssh @sshOptions $destination "bash '$remote/deploy-release.sh' staging '$snapshot'"
if ($LASTEXITCODE -ne 0) { throw 'Staging deployment failed; inspect server log and maintenance state' }
Write-Host "Staging deployed: $snapshot (local source snapshot, not a Git commit)"
