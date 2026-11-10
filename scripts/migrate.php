<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
$options=getopt('',['config:','offline']);
if (!empty($options['config'])) {
    putenv('APP_TESTING=1');
    putenv('APP_DATABASE_CONFIG='.$options['config']);
}
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Transfer.php';
require ROOT.'/app/Backup.php';
$connection=administrativeMysqlConnection();
if (legacyUserSchema($connection)) {
    if (!array_key_exists('offline',$options)) {
        fwrite(STDERR,"Detenga la aplicación y ejecute la migración con --offline. Se generará un respaldo antes de modificar el esquema.\n");
        exit(1);
    }
    $destination=ROOT.'/storage/backups/'.date('Ymd-His').'-before-identity-'.bin2hex(random_bytes(3));
    createMysqlBackup($connection,uploadDir(),$destination);
    echo "Respaldo previo verificado: $destination\n";
}
migrateDatabase($connection);
initializeBalanceHistory();
echo "Actualizaciones aplicadas sin reemplazar registros ni configuraciones existentes.\n";
