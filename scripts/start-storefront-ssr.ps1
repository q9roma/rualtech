param([switch]$Restart)

$ErrorActionPreference = 'Stop'
$projectDir = Split-Path -Parent $PSScriptRoot
$bundlePath = Join-Path $projectDir 'bootstrap/ssr/ssr.js'
$logDir = Join-Path $projectDir 'storage/logs'

# Task Scheduler also invokes this periodically to recover after a Node crash.
$mutex = [System.Threading.Mutex]::new($false, 'Local\AltechStorefrontSsrStartup')
$locked = $false
try {
    $locked = $mutex.WaitOne(30000)
    if (-not $locked) { throw 'Timed out waiting for SSR startup lock.' }

    if ($Restart) {
        # Only restart Node processes running this checkout's absolute bundle path.
        Get-CimInstance Win32_Process -Filter "Name = 'node.exe'" | Where-Object {
            $_.CommandLine -and $_.CommandLine.Contains('"' + $bundlePath + '"')
        } | ForEach-Object { Stop-Process -Id $_.ProcessId -ErrorAction Stop }
    }

    try {
        $health = Invoke-WebRequest 'http://127.0.0.1:13714/health' -UseBasicParsing -TimeoutSec 3
        if ($health.StatusCode -eq 200) { exit 0 }
    } catch { }

    # Do not duplicate a process that is still starting.
    $existing = Get-CimInstance Win32_Process -Filter "Name = 'node.exe'" | Where-Object {
        $_.CommandLine -and $_.CommandLine.Contains($bundlePath)
    }
    if ($existing) { exit 0 }
    if (-not (Test-Path -LiteralPath $bundlePath)) { throw 'SSR bundle missing. Run npm run build.' }

    $nodePath = (Get-Command node.exe -ErrorAction Stop).Source
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    $env:INERTIA_SSR_PORT = '13714'
    Start-Process -FilePath $nodePath -ArgumentList ('"{0}"' -f $bundlePath) `
        -WorkingDirectory $projectDir -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDir 'ssr-local.log') `
        -RedirectStandardError (Join-Path $logDir 'ssr-local-error.log') | Out-Null

    $ready = $false
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        try {
            $health = Invoke-WebRequest 'http://127.0.0.1:13714/health' -UseBasicParsing -TimeoutSec 1
            if ($health.StatusCode -eq 200) { $ready = $true; break }
        } catch { }
        Start-Sleep -Milliseconds 250
    }
    if (-not $ready) { throw 'SSR did not become healthy. Check storage/logs/ssr-local-error.log.' }
    Write-Output 'SSR is running with the current build.'
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
