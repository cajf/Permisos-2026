<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
db();
initializeBalanceHistory();
echo "Actualizaciones aplicadas sin reemplazar registros ni configuraciones existentes.\n";
