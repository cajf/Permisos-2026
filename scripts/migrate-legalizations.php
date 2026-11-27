<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
installLegalizationSchema(administrativeMysqlConnection());
echo "Registro de legalizaciones habilitado sin modificar solicitudes ni aprobaciones.\n";
