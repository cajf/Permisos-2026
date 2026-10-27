$ErrorActionPreference='Stop'
$mysqlProject=Split-Path -Parent $PSScriptRoot
$runtimeFile=Join-Path $mysqlProject 'storage\mysql\runtime.json'
if (!(Test-Path -LiteralPath $runtimeFile)) { throw 'Configure MySQL con .\configurar-mysql.ps1.' }
$runtime=Get-Content -LiteralPath $runtimeFile -Raw | ConvertFrom-Json
$mysqlBinary=Join-Path $runtime.basedir 'bin\mysqld.exe'
$mysqlIni=Join-Path $mysqlProject 'storage\mysql\my.ini'
$mysqlPid=Join-Path $mysqlProject 'storage\mysql\server.pid'
$mysqlProcess=$null
if(Test-Path -LiteralPath $mysqlPid){
    $mysqlProcessId=0
    if([int]::TryParse((Get-Content -LiteralPath $mysqlPid -Raw).Trim(),[ref]$mysqlProcessId)){
        $candidate=Get-Process -Id $mysqlProcessId -ErrorAction SilentlyContinue
        if($candidate -and $candidate.Path -eq $mysqlBinary){$mysqlProcess=$candidate}
    }
}
function Test-ProjectMysqlPort {
    $client=[System.Net.Sockets.TcpClient]::new()
    try{$connection=$client.ConnectAsync('127.0.0.1',[int]$runtime.port);return $connection.Wait(300) -and $client.Connected}catch{return $false}finally{$client.Dispose()}
}
function Start-ProjectMysql {
    if(Test-ProjectMysqlPort){throw "El puerto $($runtime.port) está ocupado por otro proceso. No se modificó ese servidor."}
    return Start-Process -FilePath $mysqlBinary -ArgumentList @('--defaults-file="'+$mysqlIni+'"') -WorkingDirectory $mysqlProject -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $mysqlProject 'storage\mysql\process.log') -RedirectStandardError (Join-Path $mysqlProject 'storage\mysql\process-error.log')
}
$adoptedExisting=$null -ne $mysqlProcess
if(!$mysqlProcess){$mysqlProcess=Start-ProjectMysql}
$deadline=[DateTime]::UtcNow.AddSeconds(45)
while(!(Test-ProjectMysqlPort)){
    if($mysqlProcess.HasExited){
        if($adoptedExisting){$mysqlProcess=Start-ProjectMysql;$adoptedExisting=$false;continue}
        throw 'MySQL no pudo iniciarse. Consulte storage/mysql/server-error.log.'
    }
    if([DateTime]::UtcNow -gt $deadline){throw 'MySQL aún no está disponible. Consulte storage/mysql/server-error.log.'}
    Start-Sleep -Milliseconds 300
}
Write-Output "MySQL del proyecto disponible en 127.0.0.1:$($runtime.port)."
