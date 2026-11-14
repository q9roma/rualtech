$ErrorActionPreference = 'Stop'
$backupRoot = 'C:\aggregator\data\backups\rualtech'
$ssh = "$env:WINDIR\System32\OpenSSH\ssh.exe"
$scp = "$env:WINDIR\System32\OpenSSH\scp.exe"
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null
$snapshot = (& $ssh -o BatchMode=yes -o ConnectTimeout=20 rualtech 'readlink /var/backups/rualtech/latest').Trim()
if ($LASTEXITCODE -ne 0 -or $snapshot -notmatch '^\d{8}T\d{6}Z$') { throw 'Cannot determine latest backup' }
$destination = Join-Path $backupRoot $snapshot
$pending = Join-Path $backupRoot "$snapshot.partial"
if (-not (Test-Path -LiteralPath $destination)) {
    New-Item -ItemType Directory -Path $pending -Force | Out-Null
    foreach ($name in @('manifest.json', 'database.sql.gz', 'site.tar.gz', 'config.tar.gz')) {
        & $scp -q -o BatchMode=yes -o ConnectTimeout=20 "rualtech:/var/backups/rualtech/$snapshot/$name" "$pending\$name"
        if ($LASTEXITCODE -ne 0) { throw "Backup transfer failed: $name" }
    }
    $manifest = Get-Content -LiteralPath "$pending\manifest.json" -Raw | ConvertFrom-Json
    foreach ($entry in $manifest.files.PSObject.Properties) {
        if ($entry.Name -notin @('database.sql.gz', 'site.tar.gz', 'config.tar.gz')) { throw 'Unexpected backup member' }
        $file = Join-Path $pending $entry.Name
        if ((Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLowerInvariant() -ne $entry.Value.sha256) { throw "Checksum mismatch: $($entry.Name)" }
    }
    Move-Item -LiteralPath $pending -Destination $destination
}
$savedManifest = Get-Content -LiteralPath "$destination\manifest.json" -Raw | ConvertFrom-Json
if ($savedManifest.created_at -ne $snapshot) { throw 'Backup identity mismatch' }
foreach ($name in @('database.sql.gz', 'site.tar.gz', 'config.tar.gz')) {
    $entry = $savedManifest.files.$name
    if (-not $entry -or (Get-FileHash -LiteralPath (Join-Path $destination $name) -Algorithm SHA256).Hash.ToLowerInvariant() -ne $entry.sha256) { throw "Stored backup checksum mismatch: $name" }
}
& $ssh -o BatchMode=yes -o ConnectTimeout=20 rualtech 'mkdir -p /var/lib/rualtech-monitor && touch /var/lib/rualtech-monitor/offsite-ok'
if ($LASTEXITCODE -ne 0) { throw 'Cannot acknowledge offsite copy' }
Get-ChildItem -LiteralPath $backupRoot -Directory | Where-Object { $_.Name -match '^\d{8}T\d{6}Z$' } | Sort-Object Name -Descending | Select-Object -Skip 7 | ForEach-Object {
    $resolved = [IO.Path]::GetFullPath($_.FullName)
    if ([IO.Path]::GetDirectoryName($resolved) -ne [IO.Path]::GetFullPath($backupRoot)) { throw 'Unsafe retention path' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
"$(Get-Date -Format o) Verified backup: $snapshot" | Set-Content -LiteralPath (Join-Path $backupRoot 'last-success.txt')
Write-Output "Verified backup: $destination"
