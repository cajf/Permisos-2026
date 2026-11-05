$ErrorActionPreference='Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
& .runtime/php/php.exe -c .runtime/php/php.ini tests/official-job-categories.php
if($LASTEXITCODE -ne 0){throw 'Fallaron las pruebas del catálogo oficial.'}
$previousTesting=$env:APP_TESTING;$previousConfig=$env:APP_DATABASE_CONFIG
try{
 $env:APP_TESTING='1';$env:APP_DATABASE_CONFIG=(Get-Content .work/mail-test-result.json -Raw|ConvertFrom-Json).database_config
 $server=Start-Process -FilePath '.runtime/php/php.exe' -ArgumentList @('-c','.runtime/php/php.ini','-S','127.0.0.1:8099','-t','public','public/router.php') -WindowStyle Hidden -PassThru -RedirectStandardOutput '.work/official-catalog-http.log' -RedirectStandardError '.work/official-catalog-http-errors.log'
 Start-Sleep -Milliseconds 600
 if($server.HasExited){throw 'No se pudo iniciar el servidor aislado.'}
 & .runtime/php/php.exe -c .runtime/php/php.ini tests/official-job-categories-http.php
 if($LASTEXITCODE -ne 0){throw 'Falló la comprobación HTTP.'}
}finally{if($server -and !$server.HasExited){Stop-Process -Id $server.Id};$env:APP_TESTING=$previousTesting;$env:APP_DATABASE_CONFIG=$previousConfig}
