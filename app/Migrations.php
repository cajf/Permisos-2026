<?php
declare(strict_types=1);

/** La instalación y actualización de MySQL se ejecutan explícitamente con credenciales de administración. */
function migrateDatabase(PDO $pdo): void {
    installMysqlSchema($pdo);
    seedJobCatalog($pdo);
    verifyMysqlReferences($pdo);
}
