$ErrorActionPreference = 'Stop'
$testRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $testRoot
$testPhp = Join-Path $testRoot '.runtime\php\php.exe'
$testIni = Join-Path $testRoot '.runtime\php\php.ini'
& $testPhp -c $testIni -d display_errors=1 tests/core.php
if ($LASTEXITCODE -ne 0) { throw 'Fallaron las comprobaciones funcionales.' }
& $testPhp -c $testIni -d display_errors=1 tests/catalog-migration.php
if ($LASTEXITCODE -ne 0) { throw 'Falló la migración aislada del catálogo.' }
$testResult = Get-Content -LiteralPath '.work\test-result.json' -Raw | ConvertFrom-Json
$previousTestDatabase = $env:APP_DATABASE_CONFIG
$previousTestMode = $env:APP_TESTING
$env:APP_DATABASE_CONFIG = $testResult.database_config
$env:APP_TESTING = '1'
$testProcess = $null
try {
    $testProcess = Start-Process -FilePath $testPhp -ArgumentList @('-c', ('"' + $testIni + '"'), '-S', '127.0.0.1:8089', '-t', 'public', 'public/router.php') -WorkingDirectory $testRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $testRoot '.work\test-server.log') -RedirectStandardError (Join-Path $testRoot '.work\test-server-error.log')
    Start-Sleep -Milliseconds 500
    if ($testProcess.HasExited) { throw 'No se pudo iniciar el servidor de prueba. Revise el puerto 8089.' }
    & $testPhp -c $testIni -d display_errors=1 tests/http.php
    if ($LASTEXITCODE -ne 0) { throw 'Fallaron las comprobaciones HTTP.' }
    & $testPhp -c $testIni -d display_errors=1 tests/startup.php
    if ($LASTEXITCODE -ne 0) { throw 'Fallaron las comprobaciones de arranque.' }
} finally {
    if ($testProcess -and !$testProcess.HasExited) { Stop-Process -Id $testProcess.Id }
    $env:APP_DATABASE_CONFIG = $previousTestDatabase
    $env:APP_TESTING = $previousTestMode
}
