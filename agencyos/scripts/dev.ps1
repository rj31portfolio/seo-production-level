$ErrorActionPreference = 'Stop'
$appRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $appRoot
. (Join-Path $PSScriptRoot 'use-php.ps1')

php artisan dev --no-interaction
exit $LASTEXITCODE
