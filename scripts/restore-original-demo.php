<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
if (!in_array('--confirm=RESTORE_ORIGINAL_DEMO',$argv,true)) {
    fwrite(STDERR,"Esta operación elimina los registros actuales y restaura los seis usuarios ficticios originales.\nUse --confirm=RESTORE_ORIGINAL_DEMO para ejecutarla, con respaldo previo automático.\n");exit(1);
}
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Transfer.php';
require ROOT.'/app/Backup.php';
require ROOT.'/database/original-demo.php';
$destination=ROOT.'/storage/backups/'.date('Ymd-His').'-before-demo-'.bin2hex(random_bytes(3));
$backup=createMysqlBackup(db(),uploadDir(),$destination);
$orphanFiles=[];
foreach (glob(uploadDir().'/*') as $file) if (is_file($file) && !isset($backup['files'][basename($file)])) {
    $name=basename($file);
    if (!preg_match('/^[a-f0-9]{48}$/D',$name)) throw new RuntimeException('Se encontró un archivo no reconocido: revise los soportes antes de restaurar.');
    if (!is_dir($destination.'/orphan_uploads')) mkdir($destination.'/orphan_uploads',0700);
    if (!copy($file,$destination.'/orphan_uploads/'.$name)) throw new RuntimeException('No se pudo respaldar un soporte sin registro.');
    $orphanFiles[$name]=hash_file('sha256',$file);
}
file_put_contents($destination.'/orphan_uploads.json',json_encode($orphanFiles,JSON_PRETTY_PRINT));
$access=ROOT.'/storage/acceso-demo-restaurado.txt';
// Abre el archivo privado antes de eliminar datos: no se ejecuta si no se pueden guardar las claves.
$handle=fopen($access,'wb');
if (!$handle) throw new RuntimeException('No se puede guardar el acceso de demostración.');
try {
    $credentials=restoreOriginalDemo();
    fwrite($handle,"ACCESOS LOCALES DE DEMOSTRACIÓN RESTAURADOS\nFecha: ".now()."\n\n");
    foreach ($credentials as $username=>$password) fwrite($handle,$username.': '.$password."\n");
    fwrite($handle,"\nDatos ficticios. Se exige cambiar la contraseña al ingresar. Archivo privado: no publicar.\n");
} finally {fclose($handle);}
foreach ($backup['files']+$orphanFiles as $name=>$checksum) {
    $file=uploadDir().'/'.$name;
    if (is_file($file) && hash_file('sha256',$file)===$checksum && !unlink($file)) throw new RuntimeException('Base restaurada, pero no se pudo retirar un soporte respaldado.');
}
echo "Restauración completada: 6 usuarios ficticios, 3 dependencias, 17 tipos y ningún trámite.\nRespaldo: $destination\nAccesos privados: $access\n";
