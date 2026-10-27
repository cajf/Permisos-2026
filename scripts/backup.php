<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
$dir=ROOT.'/storage/backups/'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($dir.'/uploads',0700,true);
// VACUUM INTO obtiene una fotografía consistente; los soportes no se eliminan desde la aplicación.
$snapshot=$dir.'/app.sqlite';
db()->exec('VACUUM INTO '.db()->quote($snapshot));
$copy=new PDO('sqlite:'.$snapshot);
foreach($copy->query('SELECT stored_name FROM attachments') as $file){$source=uploadDir().'/'.$file['stored_name'];ensure(is_file($source),'Falta un soporte. Revise la integridad del almacenamiento.');ensure(copy($source,$dir.'/uploads/'.$file['stored_name']),'No se pudo copiar un soporte.');}
echo 'Respaldo creado: '.$dir."\n";
