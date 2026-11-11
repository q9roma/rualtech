$ErrorActionPreference = 'Stop'
$projectDir = Split-Path -Parent $PSScriptRoot
$phpPath = Join-Path $projectDir '.tools/php84/php.exe'
$artisanPath = Join-Path $projectDir 'artisan'
$existing = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
    $_.CommandLine -like "*$artisanPath*schedule:work*"
}
$logDir = Join-Path $projectDir 'storage/logs'
if ($existing) { Write-Output 'Catalog scheduler is already running' }
else {
    $worker = Start-Process -FilePath $phpPath -ArgumentList ('"{0}" schedule:work' -f $artisanPath) -WorkingDirectory $projectDir -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logDir 'catalog-scheduler.log') -RedirectStandardError (Join-Path $logDir 'catalog-scheduler-error.log')
    Write-Output "Catalog scheduler started: PID $($worker.Id). Runs while this Windows session is active."
}
Write-Output 'The scheduler starts bounded catalog-prices workers every minute, and restarts them after normal exits.'
