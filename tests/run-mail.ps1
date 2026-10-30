$ErrorActionPreference='Stop'
$mailRoot=Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $mailRoot
$mailPhp=Join-Path $mailRoot '.runtime/php/php.exe'
$mailIni=Join-Path $mailRoot '.runtime/php/php.ini'
& $mailPhp -c $mailIni tests/mail-run.php
if($LASTEXITCODE -ne 0){throw 'Fallaron las consultas de los correos.'}
$previousListingConfig=$env:APP_DATABASE_CONFIG
$previousListingTesting=$env:APP_TESTING
$previousListingUrl=$env:APP_MAIL_TEST_URL
$mailProcess=$null
try {
 $env:APP_DATABASE_CONFIG=(Get-Content .work/mail-test-result.json -Raw|ConvertFrom-Json).database_config
 $env:APP_TESTING='1';$env:APP_MAIL_TEST_URL='http://127.0.0.1:8097'
 $mailProcess=Start-Process -FilePath $mailPhp -ArgumentList @('-c',('"'+$mailIni+'"'),'-S','127.0.0.1:8097','-t','public','public/router.php') -WorkingDirectory $mailRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $mailRoot '.work/mail-http.log') -RedirectStandardError (Join-Path $mailRoot '.work/mail-http-error.log')
 Start-Sleep -Milliseconds 500
 if($mailProcess.HasExited){throw 'No se pudo iniciar el servidor aislado en 8097.'}
 & $mailPhp -c $mailIni tests/mail-http.php
 if($LASTEXITCODE -ne 0){throw 'Fallaron las pruebas HTTP de los correos.'}
} finally {
 if($mailProcess -and !$mailProcess.HasExited){Stop-Process -Id $mailProcess.Id}
 $env:APP_DATABASE_CONFIG=$previousListingConfig;$env:APP_TESTING=$previousListingTesting;$env:APP_MAIL_TEST_URL=$previousListingUrl
}
