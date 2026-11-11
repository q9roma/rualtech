$ErrorActionPreference = 'Stop'
$projectDir = Split-Path -Parent $PSScriptRoot
$vitePath = Join-Path $projectDir 'node_modules/vite/bin/vite.js'
$mutex = [System.Threading.Mutex]::new($false, 'Local\AltechStorefrontDevStartup')
$locked = $false
try {
    $locked = $mutex.WaitOne(30000)
    if (-not $locked) { throw 'Timed out waiting for Vite startup lock.' }
    try {
        $response = Invoke-WebRequest 'http://127.0.0.1:5173/@vite/client' -UseBasicParsing -TimeoutSec 3
        if ($response.StatusCode -eq 200) { exit 0 }
    } catch { }
    $existing = Get-CimInstance Win32_Process -Filter "Name = 'node.exe'" | Where-Object {
        $_.CommandLine -and $_.CommandLine.Contains('"' + $vitePath + '"')
    }
    if ($existing) { exit 0 }
    $logDir = Join-Path $projectDir 'storage/logs'
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    Start-Process -FilePath (Get-Command node.exe).Source -ArgumentList ('"{0}"' -f $vitePath) `
        -WorkingDirectory $projectDir -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDir 'vite-local.log') `
        -RedirectStandardError (Join-Path $logDir 'vite-local-error.log') | Out-Null
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
