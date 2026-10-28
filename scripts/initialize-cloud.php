<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$options=getopt('',['config:','confirm:']);
if (($options['confirm']??'')!=='INITIALIZE_EMPTY_MYSQL' || empty($options['config'])) throw new RuntimeException('Indique --config con un archivo privado de .work y --confirm=INITIALIZE_EMPTY_MYSQL. Solo se inicializan bases vacías.');
putenv('APP_TESTING=1');putenv('APP_DATABASE_CONFIG='.$options['config']);
require __DIR__.'/../app/bootstrap.php';require ROOT.'/database/original-demo.php';
if (db()->query('SHOW TABLES')->fetchAll()!==[]) throw new RuntimeException('La base de destino no está vacía. No se modificó.');
if (empty($GLOBALS['config']['mysql']['ssl_ca'])) throw new RuntimeException('La inicialización remota requiere TLS con certificado CA.');
installMysqlSchema(db());
$credentials=restoreOriginalDemo();
$password='Cloud!'.bin2hex(random_bytes(24));$account=db()->quote('permisos_app')."@'%'";
db()->exec('CREATE USER '.$account.' IDENTIFIED BY '.db()->quote($password).' REQUIRE SSL');
db()->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON '.mysqlIdentifier($GLOBALS['config']['mysql']['database']).'.* TO '.$account);
$connection=array_replace($GLOBALS['config']['mysql'],['username'=>'permisos_app','password'=>$password]);
$app=mysqlConnection($connection);
if ((int)$app->query('SELECT COUNT(*) FROM users')->fetchColumn()!==6) throw new RuntimeException('No se pudo verificar el usuario web.');
$stem=ROOT.'/storage/cloud-'.date('Ymd-His');
file_put_contents($stem.'-config.php',"<?php\nreturn ".var_export(['mysql'=>$connection],true).";\n");
$text="ACCESOS PRIVADOS A LA DEMOSTRACIÓN EN INTERNET\n\n";
foreach ($credentials as $username=>$temporary) $text.=$username.': '.$temporary."\n";
$text.="\nCambie las contraseñas al ingresar. Contenido ficticio. No publicar este archivo.\n";
file_put_contents($stem.'-access.txt',$text);
echo "MySQL inicializado y verificado: 6 usuarios ficticios y ningún trámite.\nConfiguración y accesos privados guardados en $stem.\n";
