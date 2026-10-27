<?php
declare(strict_types=1);

/** Actualizaciones aditivas para bases ya instaladas, sin reemplazar registros. */
function migrateDatabase(PDO $pdo): void {
    $exists=$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='categories'")->fetchColumn();
    if (!$exists) return;
    $changes=[
        'categories'=>['supports_required'=>'INTEGER NOT NULL DEFAULT 1 CHECK(supports_required IN (0,1))'],
        'users'=>['birth_date'=>'TEXT'],
        'requests'=>['department_id'=>'INTEGER REFERENCES departments(id)','birthday_year'=>'INTEGER','special_note'=>"TEXT NOT NULL DEFAULT ''",'cancellation_assignee_id'=>'INTEGER REFERENCES users(id)'],
        'request_slots'=>['start_at'=>'TEXT','end_at'=>'TEXT','special_periods'=>"TEXT NOT NULL DEFAULT ''",'is_full_day'=>'INTEGER NOT NULL DEFAULT 0'],
        'approvals'=>['designation_id'=>'INTEGER REFERENCES designations(id)']
    ];
    $needed=!$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='designations'")->fetchColumn();
    foreach ($changes as $table=>$columns) {
        $existing=array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name');
        foreach ($columns as $name=>$definition) if (!in_array($name,$existing,true)) $needed=true;
    }
    if (!$needed) return;
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        foreach ($changes as $table=>$columns) {
            $existing=array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name');
            foreach ($columns as $name=>$definition) if (!in_array($name,$existing,true)) $pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$definition);
        }
        $pdo->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
        $pdo->exec("UPDATE request_slots SET start_at=date||' '||start_time||':00',end_at=date||' '||end_time||':00' WHERE start_at IS NULL");
        $pdo->exec('UPDATE requests SET department_id=(SELECT department_id FROM users WHERE users.id=requests.user_id) WHERE department_id IS NULL');
        $pdo->exec("UPDATE requests SET cancellation_assignee_id=CAST((SELECT value FROM settings WHERE key=CASE WHEN requests.user_id=CAST((SELECT value FROM settings WHERE key='subsecretary_id') AS INTEGER) THEN 'subsecretary_alternate_id' ELSE 'subsecretary_id' END) AS INTEGER) WHERE status='cancel_pending' AND cancellation_assignee_id IS NULL");
        $pdo->exec("UPDATE categories SET max_minutes=NULL,policy_confirmed=1,notes='Una jornada completa por vigencia. Nacidos en diciembre: disfrute hasta el 30 de enero siguiente con cargo a la vigencia anterior.' WHERE slug='cumpleanos'");
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}
