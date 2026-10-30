$ErrorActionPreference='Stop'
$analyticsRoot=Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $analyticsRoot
$analyticsPhp=Join-Path $analyticsRoot '.runtime/php/php.exe'
$analyticsIni=Join-Path $analyticsRoot '.runtime/php/php.ini'
& $analyticsPhp -c $analyticsIni tests/analytics-run.php
if($LASTEXITCODE -ne 0){throw 'Fallaron los cálculos del tablero.'}
$previousAnalyticsConfig=$env:APP_DATABASE_CONFIG
$previousAnalyticsTesting=$env:APP_TESTING
$previousAnalyticsUrl=$env:APP_ANALYTICS_TEST_URL
$analyticsProcess=$null
try {
 $env:APP_DATABASE_CONFIG=(Get-Content .work/analytics-test-result.json -Raw|ConvertFrom-Json).database_config
 $env:APP_TESTING='1';$env:APP_ANALYTICS_TEST_URL='http://127.0.0.1:8091'
 $analyticsProcess=Start-Process -FilePath $analyticsPhp -ArgumentList @('-c',('"'+$analyticsIni+'"'),'-S','127.0.0.1:8091','-t','public','public/router.php') -WorkingDirectory $analyticsRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $analyticsRoot '.work/analytics-http.log') -RedirectStandardError (Join-Path $analyticsRoot '.work/analytics-http-error.log')
 Start-Sleep -Milliseconds 500
 if($analyticsProcess.HasExited){throw 'No se pudo iniciar el servidor aislado en 8091.'}
 & $analyticsPhp -c $analyticsIni tests/analytics-http.php
 if($LASTEXITCODE -ne 0){throw 'Fallaron las rutas privadas del tablero.'}
} finally {
 if($analyticsProcess -and !$analyticsProcess.HasExited){Stop-Process -Id $analyticsProcess.Id}
 $env:APP_DATABASE_CONFIG=$previousAnalyticsConfig;$env:APP_TESTING=$previousAnalyticsTesting;$env:APP_ANALYTICS_TEST_URL=$previousAnalyticsUrl
}
