<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
$connection=administrativeMysqlConnection();
migrateDatabase($connection);
initializeBalanceHistory();
echo "Actualizaciones aplicadas sin reemplazar registros ni configuraciones existentes.\n";
