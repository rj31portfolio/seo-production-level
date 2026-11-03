$appRoot = Split-Path -Parent $PSScriptRoot
$localPhpDirectory = Join-Path $appRoot 'storage\framework\testing\php80'
$localPhpExecutable = Join-Path $localPhpDirectory 'php.exe'

if (-not (Test-Path -LiteralPath $localPhpExecutable)) {
    $localPhpExecutable = (Get-Command php -ErrorAction Stop).Source
    $localPhpDirectory = Split-Path -Parent $localPhpExecutable
}

$phpVersionId = & $localPhpExecutable -r 'echo PHP_VERSION_ID;'
if ($LASTEXITCODE -ne 0 -or [int]$phpVersionId -lt 80002) {
    Write-Error 'The PHP runtime must be PHP 8.0.2 or newer.'
    return
}

$currentPathEntries = $env:Path -split ';' | Where-Object { $_ -and $_.TrimEnd('\') -ne $localPhpDirectory.TrimEnd('\') }
$env:Path = (@($localPhpDirectory) + @($currentPathEntries)) -join ';'

$localTemporaryDirectory = Join-Path $appRoot 'storage\framework\testing'
if (-not (Test-Path -LiteralPath $localTemporaryDirectory)) {
    New-Item -ItemType Directory -Path $localTemporaryDirectory -Force | Out-Null
}
$temporaryPathForPhp = $localTemporaryDirectory.Replace('\', '/')
$localPhpConfiguration = Join-Path $localTemporaryDirectory 'development.ini'
@"
sys_temp_dir="$temporaryPathForPhp"
upload_tmp_dir="$temporaryPathForPhp"
"@ | Set-Content -LiteralPath $localPhpConfiguration -Encoding ASCII
$phpScanDirectories = @($env:PHP_INI_SCAN_DIR -split ';' | Where-Object { $_ -and $_ -ne $localTemporaryDirectory })
$env:PHP_INI_SCAN_DIR = (@($phpScanDirectories) + @($localTemporaryDirectory)) -join ';'

Write-Output 'This terminal now uses the local PHP runtime for PHP, Artisan, and Composer.'
& $localPhpExecutable --version
