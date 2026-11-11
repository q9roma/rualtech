$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$runtimePath = Join-Path $projectRoot '.tools/php84'
$archivePath = Join-Path $projectRoot '.tools/php84.zip'
New-Item -ItemType Directory -Force $runtimePath | Out-Null
$url = 'https://windows.php.net/downloads/releases/php-8.4.27-1-nts-Win32-vs17-x64.zip'
$sha256 = 'dbae154a0a89791f36c3d48e68bae797878333e7d6cb5721e74617de59b63ab7'
Invoke-WebRequest $url -OutFile $archivePath
if ((Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash -ne $sha256) {
    throw 'PHP archive SHA256 does not match the official release.'
}
Expand-Archive -LiteralPath $archivePath -DestinationPath $runtimePath -Force
$extensionPath = ($runtimePath.Replace('\', '/') + '/ext')
@(
    ('extension_dir="' + $extensionPath + '"'),
    'extension=curl', 'extension=fileinfo', 'extension=gd', 'extension=intl',
    'extension=mbstring', 'extension=exif', 'extension=openssl',
    'extension=pdo_mysql', 'extension=pdo_sqlite', 'extension=sqlite3', 'extension=zip',
    'memory_limit=512M', 'date.timezone=Europe/Moscow',
    'upload_max_filesize=20M', 'post_max_size=32M'
) | Set-Content (Join-Path $runtimePath 'php.ini')
& (Join-Path $runtimePath 'php.exe') -v
if ($LASTEXITCODE -ne 0) { throw 'PHP startup failed. Install the official Microsoft Visual C++ 2022 x64 runtime.' }
