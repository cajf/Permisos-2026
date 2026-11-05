$ErrorActionPreference='Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
& .runtime/php/php.exe -c .runtime/php/php.ini tests/permissions-run.php
if($LASTEXITCODE -ne 0){throw 'Fallaron las pruebas de permisos.'}
$previousTesting=$env:APP_TESTING;$previousConfig=$env:APP_DATABASE_CONFIG
try {
 $env:APP_TESTING='1';$env:APP_DATABASE_CONFIG=(Get-Content .work/permissions-test-result.json -Raw|ConvertFrom-Json).database_config
 $rbacServer=Start-Process -FilePath '.runtime/php/php.exe' -ArgumentList @('-c','.runtime/php/php.ini','-S','127.0.0.1:8098','-t','public','public/router.php') -WindowStyle Hidden -PassThru -RedirectStandardOutput '.work/rbac-http.log' -RedirectStandardError '.work/rbac-http-error.log'
 Start-Sleep -Milliseconds 600
 if($rbacServer.HasExited){throw 'No inició el servidor aislado.'}
 & .runtime/php/php.exe -c .runtime/php/php.ini tests/permissions-http.php
 if($LASTEXITCODE -ne 0){throw 'Fallaron las pruebas HTTP de permisos.'}
}finally{if($rbacServer -and !$rbacServer.HasExited){Stop-Process -Id $rbacServer.Id};$env:APP_TESTING=$previousTesting;$env:APP_DATABASE_CONFIG=$previousConfig}
