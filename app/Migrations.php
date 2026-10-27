<?php
declare(strict_types=1);

/** Actualizaciones aditivas para bases ya instaladas, sin reemplazar registros. */
function migrateDatabase(PDO $pdo): void {
    $exists=$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='categories'")->fetchColumn();
    if (!$exists) return;
    $hasColumn=static fn(): bool=>in_array('supports_required',array_column($pdo->query('PRAGMA table_info(categories)')->fetchAll(PDO::FETCH_ASSOC),'name'),true);
    if ($hasColumn()) return;
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if (!$hasColumn()) $pdo->exec('ALTER TABLE categories ADD COLUMN supports_required INTEGER NOT NULL DEFAULT 1 CHECK(supports_required IN (0,1))');
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}
