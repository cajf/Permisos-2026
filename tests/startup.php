<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$root=dirname(__DIR__);$checks=0;
function startupCheck(bool $condition,string $message):void {global $checks;if(!$condition)throw new RuntimeException('FALLO: '.$message);$checks++;echo 'OK '.$message."\n";}
function runDatabaseCheck():array {
    global $root;$pipes=[];$process=proc_open([PHP_BINARY,'-c',php_ini_loaded_file(),$root.'/scripts/check-database.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($process),$out,$error];
}
[$exit,$out,$error]=runDatabaseCheck();startupCheck($exit===0 && str_contains($out,'Conexion MySQL verificada'),'La comprobación permite iniciar con MySQL disponible');
$socket=stream_socket_server('tcp://127.0.0.1:0',$number,$message);if(!$socket)throw new RuntimeException($message);
$closedPort=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);fclose($socket);
$configuration=$root.'/.work/unavailable-'.bin2hex(random_bytes(5)).'.php';file_put_contents($configuration,'<?php return '.var_export(['mysql'=>['port'=>$closedPort]],true).';');
$previousMode=getenv('APP_TESTING');$previousConfig=getenv('APP_DATABASE_CONFIG');$process=null;$pipes=[];
try {
    putenv('APP_TESTING=1');putenv('APP_DATABASE_CONFIG='.$configuration);
    [$exit,$out,$error]=runDatabaseCheck();startupCheck($exit===1 && str_contains($error,'MySQL no acepta conexiones') && !str_contains($error,'PDOException'),'MySQL detenido bloquea el arranque con un mensaje claro');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$number,$message);$httpPort=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);fclose($socket);
    $process=proc_open([PHP_BINARY,'-c',php_ini_loaded_file(),'-S','127.0.0.1:'.$httpPort,'-t','public','public/router.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);fclose($pipes[0]);
    $deadline=microtime(true)+8;$code=0;$body='';
    do {$curl=curl_init('http://127.0.0.1:'.$httpPort.'/?page=login');curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT_MS=>300,CURLOPT_TIMEOUT=>3]);$body=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);if($code)break;usleep(100000);}while(microtime(true)<$deadline);
    startupCheck($code===503 && str_contains((string)$body,'iniciar.cmd') && !str_contains((string)$body,'PDOException') && !str_contains((string)$body,'Fatal error'),'El portal muestra una respuesta 503 comprensible si la base no está disponible');
} finally {
    if(is_resource($process)){proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);}
    putenv($previousMode===false?'APP_TESTING':'APP_TESTING='.$previousMode);putenv($previousConfig===false?'APP_DATABASE_CONFIG':'APP_DATABASE_CONFIG='.$previousConfig);
}
echo $checks." comprobaciones de arranque correctas.\n";
