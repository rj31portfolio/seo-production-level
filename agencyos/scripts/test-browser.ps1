$ErrorActionPreference = 'Stop'
$appRoot = Split-Path -Parent $PSScriptRoot
$phpRuntime = Join-Path (Split-Path -Parent $appRoot) '.tools\php\php.exe'
if (-not (Test-Path -LiteralPath $phpRuntime)) { $phpRuntime = (Get-Command php).Source }
Set-Location -LiteralPath $appRoot
if (Test-Path -LiteralPath (Join-Path (Split-Path -Parent $appRoot) '.tools\php\php.exe')) {
    . (Join-Path $PSScriptRoot 'use-php.ps1')
}
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = Join-Path $appRoot 'storage\framework\testing\seo-browser.sqlite'
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8101'
if (-not (Test-Path -LiteralPath $env:DB_DATABASE)) { New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null }
& $phpRuntime artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
node node_modules\@playwright\test\cli.js test
exit $LASTEXITCODE
