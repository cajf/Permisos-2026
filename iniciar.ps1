$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$projectPhp = Join-Path $PSScriptRoot '.runtime\php\php.exe'
if (!(Test-Path -LiteralPath $projectPhp)) { throw 'Instale PHP y use las instrucciones de README.md.' }
& (Join-Path $PSScriptRoot 'scripts\start-mysql.ps1')
& $projectPhp -c (Join-Path $PSScriptRoot '.runtime\php\php.ini') -S 127.0.0.1:8088 -t public public/router.php
