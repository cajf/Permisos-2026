<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
$admin=require ROOT.'/storage/mysql/admin.php';mysqlConnection($admin,false)->exec('SHUTDOWN');
echo "Se solicitó el cierre ordenado del MySQL de este proyecto.\n";
