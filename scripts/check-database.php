<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
try {
    $connection=db();
    ensure(tableExists('users') && tableExists('app_locks'),'Falta inicializar el esquema. Consulte las instrucciones de instalacion.');
    ensure($connection->query('SELECT id FROM app_locks WHERE id=1')->fetchColumn()!==false,'Falta inicializar el control de transacciones.');
    echo 'Conexion MySQL verificada en '.$GLOBALS['config']['mysql']['host'].':'.(int)$GLOBALS['config']['mysql']['port'].".\n";
} catch (Throwable $error) {
    $code=$error instanceof PDOException?(int)($error->errorInfo[1]??0):0;
    $message=match($code){
        2002,2003=>'MySQL no acepta conexiones. Ejecute iniciar.cmd y revise storage/mysql/server-error.log.',
        1045=>'MySQL rechazo las credenciales. Revise config/local.php sin cambiar las claves de los funcionarios.',
        1049=>'No existe la base MySQL configurada. Revise el nombre de la base y el procedimiento de instalacion.',
        default=>'No se pudo verificar el acceso y el esquema MySQL. Revise la configuracion y los registros.'
    };
    fwrite(STDERR,$message."\n");exit(1);
}
