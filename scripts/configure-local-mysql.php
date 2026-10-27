<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
$private=ROOT.'/storage/mysql';if (!is_dir($private)) mkdir($private,0700,true);
$adminFile=$private.'/admin.php';$connectionFile=$private.'/connection.php';
$admin=is_file($adminFile)?require $adminFile:array_replace($GLOBALS['config']['mysql'],['username'=>'root','password'=>'']);
$pdo=mysqlConnection($admin,false);
if (!is_file($adminFile)) {
    $password=bin2hex(random_bytes(32));
    $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($password));
    $admin['password']=$password;file_put_contents($adminFile,"<?php\nreturn ".var_export($admin,true).";\n");
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS '.mysqlIdentifier($admin['database']).' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin');
$database=mysqlConnection($admin);installMysqlSchema($database);
$app=is_file($connectionFile)?require $connectionFile:['mysql'=>array_replace($admin,['username'=>'permisos_app','password'=>bin2hex(random_bytes(32))]),'uploads'=>ROOT.'/storage/uploads'];
$account=$pdo->quote($app['mysql']['username'])."@'127.0.0.1'";
$pdo->exec('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.$pdo->quote($app['mysql']['password']));
$pdo->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON '.mysqlIdentifier($app['mysql']['database']).'.* TO '.$account);
file_put_contents($connectionFile,"<?php\nreturn ".var_export($app,true).";\n");
file_put_contents($private.'/runtime.json',json_encode(['version'=>'8.4.13','port'=>(int)$admin['port'],'basedir'=>realpath(ROOT.'/.runtime/mysql-8.4.13-winx64')],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "MySQL local configurado. Credenciales privadas en storage/mysql; aplicación con usuario de permisos limitados.\n";
