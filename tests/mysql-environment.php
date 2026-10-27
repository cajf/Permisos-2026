<?php
declare(strict_types=1);
$project=dirname(__DIR__);require_once $project.'/app/Database.php';
$adminConfig=require $project.'/storage/mysql/admin.php';$admin=mysqlConnection($adminConfig,false);
$suffix=bin2hex(random_bytes(5));$name='permisos_test_'.$suffix;$username='th_test_'.$suffix;$password=bin2hex(random_bytes(24));
$admin->exec('CREATE DATABASE '.mysqlIdentifier($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin');
$account=$admin->quote($username)."@'127.0.0.1'";
$admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));
$admin->exec('GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES ON '.mysqlIdentifier($name).'.* TO '.$account);
$configuration=['mysql'=>array_replace($adminConfig,['database'=>$name,'username'=>$username,'password'=>$password]),'uploads'=>$project.'/.work/uploads-'.$suffix,'access_file'=>$project.'/.work/access-'.$suffix.'.txt'];
$path=$project.'/.work/mysql-test-'.$suffix.'.php';file_put_contents($path,"<?php\nreturn ".var_export($configuration,true).";\n");
putenv('APP_TESTING=1');putenv('APP_DATABASE_CONFIG='.$path);
$GLOBALS['test_configuration_file']=$path;
