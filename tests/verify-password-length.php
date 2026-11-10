<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Admin.php';

foreach (['abcdefg1','abcdefghijklmn1',temporaryPassword()] as $valid) {
    validatePassword($valid);
}
foreach (['abcdef1','abcdefghijklmno1','12345678','abcdefgh'] as $invalid) {
    try {
        validatePassword($invalid);
        throw new RuntimeException('Se aceptó una contraseña fuera de los requisitos.');
    } catch (DomainException) {}
}
echo "Límites de 8 y 15 caracteres y contraseñas temporales verificados.\n";
