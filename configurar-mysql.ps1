param([string]$PhpExecutable='')
$ErrorActionPreference='Stop'
Set-Location -LiteralPath $PSScriptRoot
if(!$PhpExecutable){$PhpExecutable=Join-Path $PSScriptRoot '.runtime\php\php.exe'}
if(!(Test-Path -LiteralPath $PhpExecutable)){throw 'Indique -PhpExecutable con la ruta de PHP 8.4 y habilite PDO MySQL y PDO SQLite para importar.'}
$ini=Join-Path $PSScriptRoot '.runtime\php\php.ini'
if(Test-Path -LiteralPath $ini){
    if((Get-Content -LiteralPath $ini -Raw) -notmatch '(?m)^extension=pdo_mysql\s*$'){Add-Content -LiteralPath $ini -Value 'extension=pdo_mysql'}
}
$mysqlVersion='8.4.13'
$mysqlFolder=Join-Path $PSScriptRoot ".runtime\mysql-$mysqlVersion-winx64"
$mysqlBinary=Join-Path $mysqlFolder 'bin\mysqld.exe'
if(!(Test-Path -LiteralPath $mysqlBinary)){
    New-Item -ItemType Directory -Path .tools -Force | Out-Null
    $archive=Join-Path $PSScriptRoot ".tools\mysql-$mysqlVersion-winx64.zip"
    Invoke-WebRequest -Uri "https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-$mysqlVersion-winx64.zip" -OutFile $archive -UseBasicParsing
    if((Get-FileHash -LiteralPath $archive -Algorithm MD5).Hash.ToLowerInvariant() -ne '7b94489d5524dea9b27097068bea25a8'){throw 'No coincide la comprobación oficial de MySQL.'}
    Expand-Archive -LiteralPath $archive -DestinationPath .runtime
}
New-Item -ItemType Directory -Path storage\mysql -Force | Out-Null
$mysqlIni=Join-Path $PSScriptRoot 'storage\mysql\my.ini'
if(!(Test-Path -LiteralPath $mysqlIni)){
    $rootPath=$PSScriptRoot.Replace('\','/')
    $iniText=@"
[mysqld]
basedir=$rootPath/.runtime/mysql-$mysqlVersion-winx64
datadir=$rootPath/storage/mysql/data
port=3310
bind-address=127.0.0.1
mysqlx=OFF
local-infile=OFF
character-set-server=utf8mb4
collation-server=utf8mb4_0900_bin
innodb-buffer-pool-size=128M
log-error=$rootPath/storage/mysql/server-error.log
pid-file=$rootPath/storage/mysql/server.pid
"@
    [System.IO.File]::WriteAllText($mysqlIni,$iniText,[System.Text.UTF8Encoding]::new($false))
}
if(!(Test-Path -LiteralPath storage\mysql\data\auto.cnf)){
    & $mysqlBinary ('--defaults-file='+$mysqlIni) --initialize-insecure
    if($LASTEXITCODE -ne 0){throw 'Falló la inicialización de MySQL.'}
}
$runtimeJson=@{version=$mysqlVersion;port=3310;basedir=$mysqlFolder} | ConvertTo-Json
[System.IO.File]::WriteAllText((Join-Path $PSScriptRoot 'storage\mysql\runtime.json'),$runtimeJson,[System.Text.UTF8Encoding]::new($false))
& .\scripts\start-mysql.ps1
& $PhpExecutable -c $ini scripts/configure-local-mysql.php
if($LASTEXITCODE -ne 0){throw 'No se pudo configurar la base MySQL.'}
Write-Output 'Servidor preparado. Para conservar datos existentes ejecute scripts/migrate-sqlite-to-mysql.php --activate. Para una instalación nueva, configure config/local.php con storage/mysql/connection.php y ejecute scripts/install.php.'
