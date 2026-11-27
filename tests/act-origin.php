<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';
installMysqlSchema(db());
insert('departments',['name'=>'Dependencia de prueba']);
foreach(range(1,5) as $i) fixtureUser(['name'=>'Prueba '.$i,'identification'=>'ACT-'.$i,'username'=>'acto'.$i,'email'=>'acto'.$i.'@ejemplo.invalid','password'=>password_hash('PruebaLocal9876',PASSWORD_DEFAULT),'job_title'=>'Prueba','department_id'=>1,'manager_id'=>null,'roles'=>$i===5?'["payroll"]':'[]','force_password'=>0]);
foreach(require ROOT.'/database/catalog.php' as $c)insert('categories',array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$c));
run("UPDATE categories SET supports_required=0 WHERE slug='acto'");
$wf=new Workflow();$checks=0;
function check(bool $ok,string $message):void {global $checks;ensure($ok,$message);$checks++;echo "OK $message\n";}
function refuses(callable $f,string $message):void {try{$f();}catch(DomainException){check(true,$message);return;}throw new RuntimeException($message);}
function person(int $id):array{return row('SELECT * FROM user_profiles WHERE id=?',[$id]);}
function slot(string $date,string $start,string $end):array{return ['date'=>$date,'start'=>$start,'end'=>$end,'mode'=>'hours'];}
function testSubmit(int $uid,string $slug,array $slots,array $allocations=[]):int {
 global $wf;
 return $wf->submit(person($uid),['category_id'=>row('SELECT id FROM categories WHERE slug=?',[$slug])['id'],'slots'=>$slots,'allocations'=>$allocations,'justification'=>'Justificación de prueba','exception_reason'=>'Excepción de prueba'],[],null,new DateTimeImmutable('2026-11-27 06:00:00'));
}
// Designaciones de prueba para poder radicar sin modificar datos de la base real.
run('UPDATE person_employments SET manager_id=2');setSetting('subsecretary_id','3');
insert('user_roles',['user_id'=>2,'role_code'=>'manager']);insert('user_roles',['user_id'=>3,'role_code'=>'subsecretary']);
// La migración es repetible y no cambia los saldos históricos.
$old=insert('acts',['user_id'=>1,'number'=>'HISTORICO','issued_on'=>date('Y-m-d'),'expires_on'=>'2027-12-31','granted_minutes'=>480,'created_at'=>now()]);
db()->exec('ALTER TABLE acts DROP COLUMN origin');
migrateActOrigin(db());migrateActOrigin(db());
check(row('SELECT origin FROM acts WHERE id=?',[$old])['origin']===null,'Migración conserva origen desconocido de actos previos');
check((int)row('SELECT granted_minutes FROM acts WHERE id=?',[$old])['granted_minutes']===480,'Migración no cambia horas');
foreach(['','otro'] as $origin) refuses(fn()=>$wf->act(person(1),['origin'=>$origin],[]),'Rechaza origen vacío o inválido');
$path=ROOT.'/.work/act-origin-support.txt';file_put_contents($path,'Soporte de prueba');
$GLOBALS['config']['attachment_storage']='mysql';
$files=[['tmp'=>$path,'original_name'=>'soporte.pdf','mime'=>'application/pdf','size'=>filesize($path),'sha256'=>hash_file('sha256',$path)]];
foreach(['bicycle','supplementary'] as $origin) {
 $id=$wf->act(person(1),['origin'=>$origin,'number'=>'ORIGEN-'.$origin,'issued_on'=>date('Y-m-d'),'expires_on'=>'2027-12-31','hours'=>'8'],$files);
 check(row('SELECT origin FROM acts WHERE id=?',[$id])['origin']===$origin,'Guarda origen '.$origin);
 $wf->reviewAct(person(5),$id,'approved','Validado para prueba');
 $request=testSubmit(1,'acto',[slot($origin==='bicycle'?'2026-12-01':'2026-12-02','07:30','08:30')],[['id'=>$id,'hours'=>'1']]);
 check($wf->available(row('SELECT * FROM acts WHERE id=?',[$id]))===420,'Reserva horas para '.$origin);
}
refuses(fn()=>testSubmit(1,'bici',[slot('2026-12-03','07:30','08:30')]),'Impide enviar categoría independiente oculta');
check((int)row("SELECT COUNT(*) n FROM categories WHERE slug='bici'")['n']===1,'Conserva categoría histórica');
echo "Origen de actos: pruebas completadas.\n";
$alloc=[['origin'=>'supplementary','minutes'=>60],['origin'=>'bicycle','minutes'=>120],['origin'=>null,'minutes'=>60]];
check(actRequestOrigin($alloc)==='mixed','Clasifica combinación de orígenes sin duplicar solicitud');
check(actOriginSeconds($alloc,1800,7200)===['supplementary'=>1800,'bicycle'=>5400,'unknown'=>0],'Distribuye tramo parcial por orden de asignación');
check(actOriginSeconds($alloc,9000,5400)===['supplementary'=>0,'bicycle'=>1800,'unknown'=>3600],'Separa vigencias sin reiniciar el consumo de actos');
$acts=rows("SELECT id FROM acts WHERE number LIKE 'ORIGEN-%' ORDER BY id");
$mixed=testSubmit(1,'acto',[slot('2026-12-03','07:30','09:30')],[['id'=>$acts[0]['id'],'hours'=>'1'],['id'=>$acts[1]['id'],'hours'=>'1']]);
foreach(rows('SELECT id FROM requests') as $r){$wf->review(person(2),(int)$r['id'],'approved','');$wf->review(person(3),(int)$r['id'],'approved','');}
$data=analyticsData(['metric'=>'approved','year'=>'2026','department'=>'all'],new DateTimeImmutable('2026-11-27 10:00:00'));
check($data['act_origins']['requests']===['supplementary'=>1,'bicycle'=>1,'mixed'=>1,'unknown'=>0],'Tablero cuenta tres solicitudes incluyendo una mixta solo una vez');
check($data['act_origins']['seconds']===['supplementary'=>7200,'bicycle'=>7200,'unknown'=>0],'Tablero desglosa horas de solicitudes puras y mixtas');
$data=analyticsData(['metric'=>'enjoyed','year'=>'2026','department'=>'all'],new DateTimeImmutable('2026-12-03 08:00:00'));
check($data['act_origins']['seconds']===['supplementary'=>3600,'bicycle'=>5400,'unknown'=>0],'Disfrute parcial respeta orden de asignación');
$wf->cancel(person(1),$mixed,'Cancelación de prueba de disfrute parcial');
$wf->reviewCancellation(person(3),$mixed,true,'Autorizada cancelación de prueba',new DateTimeImmutable('2026-12-03 08:00:00'));
$after=analyticsData(['metric'=>'enjoyed','year'=>'2026','department'=>'all'],new DateTimeImmutable('2026-12-04 10:00:00'));
check($after['act_origins']['seconds']===$data['act_origins']['seconds'],'Cancelación parcial conserva el desglose y excluye horas reintegradas');
