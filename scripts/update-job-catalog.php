<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$options=getopt('',['config:']);
if (!empty($options['config'])) {putenv('APP_TESTING=1');putenv('APP_DATABASE_CONFIG='.$options['config']);}
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Transfer.php';require ROOT.'/app/Backup.php';
$pdo=administrativeMysqlConnection();
$destination=ROOT.'/storage/backups/'.date('Ymd-His').'-job-catalog-'.bin2hex(random_bytes(3));
createMysqlBackup($pdo,uploadDir(),$destination);
migrateDatabase($pdo);
echo "Respaldo verificado y catálogo actualizado sin eliminar datos.\n";
echo 'Cargos: '.$pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn().' · Grados: '.$pdo->query('SELECT COUNT(*) FROM job_grades')->fetchColumn()."\n";
