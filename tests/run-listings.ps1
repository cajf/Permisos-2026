$ErrorActionPreference='Stop'
$listingRoot=Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $listingRoot
$listingPhp=Join-Path $listingRoot '.runtime/php/php.exe'
$listingIni=Join-Path $listingRoot '.runtime/php/php.ini'
& $listingPhp -c $listingIni tests/listings-run.php
if($LASTEXITCODE -ne 0){throw 'Fallaron las consultas de los listados.'}
$previousListingConfig=$env:APP_DATABASE_CONFIG
$previousListingTesting=$env:APP_TESTING
$previousListingUrl=$env:APP_LISTINGS_TEST_URL
$listingProcess=$null
try {
 $env:APP_DATABASE_CONFIG=(Get-Content .work/listings-test-result.json -Raw|ConvertFrom-Json).database_config
 $env:APP_TESTING='1';$env:APP_LISTINGS_TEST_URL='http://127.0.0.1:8094'
 $listingProcess=Start-Process -FilePath $listingPhp -ArgumentList @('-c',('"'+$listingIni+'"'),'-S','127.0.0.1:8094','-t','public','public/router.php') -WorkingDirectory $listingRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $listingRoot '.work/listings-http.log') -RedirectStandardError (Join-Path $listingRoot '.work/listings-http-error.log')
 Start-Sleep -Milliseconds 500
 if($listingProcess.HasExited){throw 'No se pudo iniciar el servidor aislado en 8094.'}
 & $listingPhp -c $listingIni tests/listings-http.php
 if($LASTEXITCODE -ne 0){throw 'Fallaron las pruebas HTTP de los listados.'}
} finally {
 if($listingProcess -and !$listingProcess.HasExited){Stop-Process -Id $listingProcess.Id}
 $env:APP_DATABASE_CONFIG=$previousListingConfig;$env:APP_TESTING=$previousListingTesting;$env:APP_LISTINGS_TEST_URL=$previousListingUrl
}
