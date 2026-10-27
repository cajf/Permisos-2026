<?php
declare(strict_types=1);

/** Actualizaciones para bases instaladas, conservando registros y metadatos retirados en la copia histórica. */
function migrateDatabase(PDO $pdo): void {
    $exists=$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='categories'")->fetchColumn();
    if (!$exists) return;
    $changes=[
        'categories'=>['supports_required'=>'INTEGER NOT NULL DEFAULT 1 CHECK(supports_required IN (0,1))'],
        'users'=>['birth_date'=>'TEXT'],
        'requests'=>['department_id'=>'INTEGER REFERENCES departments(id)','birthday_year'=>'INTEGER','cancellation_assignee_id'=>'INTEGER REFERENCES users(id)'],
        'request_slots'=>['start_at'=>'TEXT','end_at'=>'TEXT','is_full_day'=>'INTEGER NOT NULL DEFAULT 0'],
        'approvals'=>['designation_id'=>'INTEGER REFERENCES designations(id)']
    ];
    $needed=!$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='designations'")->fetchColumn();
    $requestColumns=array_column($pdo->query('PRAGMA table_info(requests)')->fetchAll(PDO::FETCH_ASSOC),'name');
    $slotColumns=array_column($pdo->query('PRAGMA table_info(request_slots)')->fetchAll(PDO::FETCH_ASSOC),'name');
    $hasNote=in_array('special_note',$requestColumns,true);$hasPeriods=in_array('special_periods',$slotColumns,true);
    $applyBirthdayRule=!in_array('birthday_year',$requestColumns,true);
    $needed=$needed || $hasNote || $hasPeriods;
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
        if ($applyBirthdayRule) $pdo->exec("UPDATE categories SET max_minutes=NULL,policy_confirmed=1,notes='Una jornada completa por vigencia. Nacidos en diciembre: disfrute hasta el 30 de enero siguiente con cargo a la vigencia anterior.' WHERE slug='cumpleanos'");
        if ($hasNote || $hasPeriods) {
            $affected=[];$legacySlots=[];
            if ($hasPeriods) foreach ($pdo->query("SELECT * FROM request_slots WHERE special_periods!=''")->fetchAll(PDO::FETCH_ASSOC) as $slot) {
                $legacySlots[(int)$slot['request_id']][]=$slot;$affected[(int)$slot['request_id']]=true;
            }
            if ($hasNote) foreach ($pdo->query("SELECT id FROM requests WHERE special_note!=''")->fetchAll(PDO::FETCH_ASSOC) as $request) $affected[(int)$request['id']]=true;
            $select=$pdo->prepare('SELECT * FROM requests WHERE id=?');$update=$pdo->prepare('UPDATE requests SET snapshot=? WHERE id=?');
            foreach (array_keys($affected) as $requestId) {
                $select->execute([$requestId]);$request=$select->fetch(PDO::FETCH_ASSOC);
                $snapshot=json_decode($request['snapshot'],true,512,JSON_THROW_ON_ERROR);
                if (!is_array($snapshot)) throw new RuntimeException('No se puede conservar la copia histórica de la solicitud '.$requestId.'.');
                $snapshot['archived_data']['schedule_before_removal']=['archived_at'=>date(DATE_ATOM),'note'=>$request['special_note']??'','slots'=>$legacySlots[$requestId]??[]];
                $update->execute([json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$requestId]);
            }
            if ($hasNote) $pdo->exec('ALTER TABLE requests DROP COLUMN special_note');
            if ($hasPeriods) $pdo->exec('ALTER TABLE request_slots DROP COLUMN special_periods');
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}
