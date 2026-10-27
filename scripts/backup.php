<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Transfer.php';require ROOT.'/app/Backup.php';
$dir=ROOT.'/storage/backups/'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
$result=createMysqlBackup(db(),uploadDir(),$dir);
echo 'Respaldo MySQL y '.count($result['files']).' soportes verificados: '.$dir."\n";
