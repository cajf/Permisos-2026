<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';require ROOT.'/app/Admin.php';require ROOT.'/database/holidays.php';
installMysqlSchema(db());
require_once ROOT.'/app/LegacySqlite.php';
insert('departments',['name'=>'Dependencia de prueba']);
foreach ([['Funcionario','[]',''],['Jefe','["manager"]',''],['Subsecretario','["subsecretary","manager"]',''],['Alcalde','["mayor","manager"]',''],['Administrador','["admin","payroll"]',''],['Asesor','[]','Asesor de Despacho'],['Otro administrador','["admin","manager"]',''],['Otro funcionario','[]','']] as $i=>$p) insert('users',['name'=>$p[0],'identification'=>'PRUEBA-'.($i+1),'username'=>'qa'.($i+1),'email'=>'qa'.($i+1).'@ejemplo.invalid','password'=>password_hash('PruebaLocal!9876',PASSWORD_DEFAULT),'job_title'=>$p[0],'department_id'=>1,'manager_id'=>null,'roles'=>$p[1],'high_rank'=>$p[2],'force_password'=>0]);
run('UPDATE users SET manager_id=2');
setSetting('subsecretary_id','3');setSetting('mayor_id','4');
foreach(require ROOT.'/database/catalog.php' as $c)insert('categories',array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$c));
for($y=2026;$y<=2028;$y++)foreach(nationalHolidays($y)as $date=>$name)run('INSERT INTO holidays(date,name)VALUES(?,?) ON DUPLICATE KEY UPDATE name=name',[$date,$name]);
$wf=new Workflow();$checks=0;
function check(bool $condition,string $label):void{global $checks;ensure($condition,'FALLO: '.$label);$checks++;echo 'OK '.$label."\n";}
function refuses(callable $fn,string $label):void{try{$fn();}catch(DomainException){check(true,$label);return;}throw new RuntimeException('FALLO: '.$label);}
function person(int $id):array{return row('SELECT u.*,d.name department FROM users u JOIN departments d ON d.id=u.department_id WHERE u.id=?',[$id]);}
function cat(string $slug):array{return row('SELECT * FROM categories WHERE slug=?',[$slug]);}
function testSubmit(int $uid,string $slug,array $slots,array $allocations=[],?DateTimeImmutable $clock=null):int{
 global $wf;$u=person($uid);$c=cat($slug);
 $stub=insert('requests',['user_id'=>$uid,'category_id'=>$c['id'],'status'=>'returned','justification'=>'Solicitud de prueba','total_minutes'=>1,'snapshot'=>'{}','revision'=>0,'created_at'=>now(),'updated_at'=>now()]);
 insert('attachments',['user_id'=>$uid,'request_id'=>$stub,'original_name'=>'soporte-prueba.pdf','stored_name'=>'test-'.bin2hex(random_bytes(8)),'mime'=>'application/pdf','size'=>100,'sha256'=>str_repeat('0',64),'created_at'=>now()]);
 try{return $wf->submit($u,['category_id'=>$c['id'],'slots'=>$slots,'justification'=>'Justificación de prueba funcional','exception_reason'=>'Excepción justificada para probar el trámite','relationship'=>'Padre','allocations'=>$allocations],[],$stub,$clock??new DateTimeImmutable('2026-10-26 06:00:00'));}
 catch(Throwable $e){run('DELETE FROM attachments WHERE request_id=?',[$stub]);run('DELETE FROM requests WHERE id=?',[$stub]);throw $e;}
}
function slot(string $date,string $start,string $end):array{return ['date'=>$date,'start'=>$start,'end'=>$end,'mode'=>'hours'];}

function approvalId(int $request,int $step=1):int{return (int)row('SELECT id FROM approvals WHERE request_id=? AND step=? ORDER BY revision DESC LIMIT 1',[$request,$step])['id'];}
require __DIR__.'/listings.php';
