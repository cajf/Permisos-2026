$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$projectPhp = Join-Path $PSScriptRoot '.runtime\php\php.exe'
if (!(Test-Path -LiteralPath $projectPhp)) { throw 'Instale PHP y use las instrucciones de README.md.' }
& (Join-Path $PSScriptRoot 'scripts\start-mysql.ps1')
& $projectPhp -c (Join-Path $PSScriptRoot '.runtime\php\php.ini') (Join-Path $PSScriptRoot 'scripts\check-database.php')
if ($LASTEXITCODE -ne 0) { throw 'No se pudo comprobar la base de datos. El servidor web no se inicio.' }
& $projectPhp -c (Join-Path $PSScriptRoot '.runtime\php\php.ini') -S 127.0.0.1:8088 -t public public/router.php
if ($LASTEXITCODE -ne 0) { throw 'El servidor PHP termino con un error. Revise el puerto 8088.' }
