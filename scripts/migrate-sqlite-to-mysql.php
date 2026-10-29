<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/LegacySqlite.php';require ROOT.'/app/Transfer.php';
$options=getopt('',['source:','source-uploads:','config:','activate']);
$sourceFile=realpath($options['source']??ROOT.'/storage/app.sqlite');
if (!$sourceFile || !is_file($sourceFile)) throw new RuntimeException('No se encontró la base SQLite de origen.');
$targetConfigFile=$options['config']??ROOT.'/storage/mysql/connection.php';
if (!is_file($targetConfigFile)) throw new RuntimeException('Configure primero el servidor MySQL de destino.');
$targetConfig=array_replace_recursive($GLOBALS['config'],require $targetConfigFile);
$sourceUploads=$options['source-uploads']??dirname($sourceFile).'/uploads';
$snapshotFile=ROOT.'/storage/mysql/source-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';
$original=new PDO('sqlite:'.$sourceFile,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$original->exec('VACUUM INTO '.$original->quote($snapshotFile));unset($original);
$source=new PDO('sqlite:'.$snapshotFile,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
migrateLegacySqlite($source);
$result=transferSqliteToMysql($source,mysqlConnection($targetConfig['mysql']),$sourceUploads,$targetConfig['uploads']);
seedJobCatalog(mysqlConnection($targetConfig['mysql']));
$result['source_snapshot']=$snapshotFile;$result['destination']=$targetConfig['mysql']['database'];
$report=ROOT.'/storage/mysql/migration-'.date('Ymd-His').'.json';file_put_contents($report,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
if (isset($options['activate'])) {
    $local=is_file(ROOT.'/config/local.php')?require ROOT.'/config/local.php':[];
    if ($local) copy(ROOT.'/config/local.php',ROOT.'/storage/mysql/config-before-migration-'.date('Ymd-His').'.php');
    $local=array_replace_recursive($local,['mysql'=>$targetConfig['mysql'],'uploads'=>$targetConfig['uploads']]);unset($local['database']);
    $temporary=ROOT.'/config/local.php.tmp';file_put_contents($temporary,"<?php\nreturn ".var_export($local,true).";\n");rename($temporary,ROOT.'/config/local.php');
}
echo 'Migración verificada: '.array_sum($result['tables']).' registros en '.count($result['tables']).' tablas y '.$result['files_verified']." soportes íntegros.\n";
echo 'Informe privado: '.$report."\n";
echo isset($options['activate'])?"Aplicación configurada para MySQL. Las contraseñas de los funcionarios se conservan.\n":"Datos copiados. Use --activate para cambiar la conexión únicamente después de verificar el destino.\n";
