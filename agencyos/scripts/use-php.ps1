$appRoot = Split-Path -Parent $PSScriptRoot
$localPhpDirectory = Join-Path (Split-Path -Parent $appRoot) '.tools\php'
$localPhpExecutable = Join-Path $localPhpDirectory 'php.exe'

if (-not (Test-Path -LiteralPath $localPhpExecutable)) {
    Write-Error 'The local PHP runtime was not found. Install PHP 8.4 and add its directory to PATH.'
    return
}

$phpVersionId = & $localPhpExecutable -r 'echo PHP_VERSION_ID;'
if ($LASTEXITCODE -ne 0 -or [int]$phpVersionId -lt 80400) {
    Write-Error 'The local PHP runtime must be PHP 8.4 or newer.'
    return
}

$currentPathEntries = $env:Path -split ';' | Where-Object { $_ -and $_.TrimEnd('\') -ne $localPhpDirectory.TrimEnd('\') }
$env:Path = (@($localPhpDirectory) + @($currentPathEntries)) -join ';'

Write-Output 'This terminal now uses the local PHP runtime for PHP, Artisan, and Composer.'
& $localPhpExecutable --version
