<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$options=getopt('',['config:']);if(!empty($options['config'])){putenv('APP_TESTING=1');putenv('APP_DATABASE_CONFIG='.$options['config']);}
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/Backup.php';require ROOT.'/app/Transfer.php';
$pdo=administrativeMysqlConnection();$destination=ROOT.'/storage/backups/'.date('Ymd-His').'-official-categories-'.bin2hex(random_bytes(3));
createMysqlBackup($pdo,uploadDir(),$destination);
migrateOfficialJobCategories($pdo);verifyMysqlReferences($pdo);
echo "Esquema actualizado y respaldo verificado. No se asignaron categorías inventadas ni se modificaron solicitudes históricas.\n";
