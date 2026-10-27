<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/Transfer.php';require ROOT.'/app/Backup.php';
$options=getopt('',['backup:','config:','uploads:']);
if (empty($options['backup']) || empty($options['config']) || empty($options['uploads'])) throw new RuntimeException('Use --backup=CARPETA --config=ARCHIVO_PHP_CONEXION --uploads=CARPETA_DESTINO. La base de destino debe estar vacía.');
$configuration=require $options['config'];$connection=$configuration['mysql']??$configuration;
restoreMysqlBackup(mysqlConnection($connection,true,true),$options['backup'],$options['uploads']);
echo "Base y soportes restaurados con conteos, integridad referencial y comprobaciones SHA-256 verificados.\n";
