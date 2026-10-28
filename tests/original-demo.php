<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/database/original-demo.php';
installMysqlSchema(db());
$credentials=restoreOriginalDemo();
assert(count($credentials)===6);
assert((int)row('SELECT COUNT(*) n FROM users')['n']===6);
assert((int)row('SELECT COUNT(*) n FROM departments')['n']===3);
assert((int)row('SELECT COUNT(*) n FROM categories')['n']===17);
$generation=setting('session_generation');
foreach (rows('SELECT * FROM users') as $person) {
    assert(password_verify($credentials[$person['username']],$person['password']));
    assert((int)$person['force_password']===1);
    assert($person['identification']==='DEMO-'.$person['id']);
}
insert('departments',['name'=>'Registro posterior que debe eliminarse']);
insert('outbox',['recipient'=>'prueba@ejemplo.invalid','subject'=>'Dato posterior','body'=>'Prueba','created_at'=>now()]);
insert('events',['actor_id'=>1,'action'=>'Dato posterior','detail'=>'Prueba','created_at'=>now()]);
$next=restoreOriginalDemo();
assert(setting('session_generation')!==$generation);
assert($credentials['admin']!==$next['admin']);
assert((int)row('SELECT COUNT(*) n FROM departments')['n']===3);
foreach (['requests','acts','events','outbox','attachments','designations','balance_snapshots'] as $table) assert((int)row('SELECT COUNT(*) n FROM '.mysqlIdentifier($table))['n']===0);
verifyMysqlReferences(db());
echo "Restauración original verificada en base aislada: datos, claves, sesiones y referencias.\n";
