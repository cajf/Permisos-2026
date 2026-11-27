<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
migrateActOrigin(administrativeMysqlConnection());
echo "Origen de actos administrativos actualizado; registros históricos conservados.\n";
