<?php
declare(strict_types=1);
require __DIR__.'/mail-run.php';
function approvedAct(int $owner,string $number,int $minutes=1200):int {global $wf;$id=insert('acts',['user_id'=>$owner,'number'=>$number,'issued_on'=>date('Y-m-d'),'expires_on'=>'2027-12-31','granted_minutes'=>$minutes,'created_at'=>now()]);$wf->reviewAct(person(5),$id,'approved','Verificado por Nómina para pruebas');return $id;}
$a=approvedAct(1,'COMBINAR-A');$b=approvedAct(1,'COMBINAR-B');
$id=testSubmit(1,'acto',[slot('2026-12-01','07:30','17:00')],[['id'=>$a,'hours'=>'3'],['id'=>$b,'hours'=>'5.5']]);
check((int)row('SELECT total_minutes FROM requests WHERE id=?',[$id])['total_minutes']===510&&count(rows('SELECT * FROM allocations WHERE request_id=?',[$id]))===2,'Combina dos actos en una jornada ordinaria de 8,5 horas');
$wf->review(person(2),$id,'approved','');$wf->review(person(3),$id,'approved','');
check($wf->available(row('SELECT * FROM acts WHERE id=?',[$a]))===1020&&$wf->available(row('SELECT * FROM acts WHERE id=?',[$b]))===870,'Descuenta por separado las horas de cada acto');
$several=testSubmit(1,'acto',[slot('2026-12-14','07:30','08:30'),slot('2026-12-15','07:30','08:30')],[['id'=>$a,'hours'=>'1'],['id'=>$b,'hours'=>'1']]);check((int)row('SELECT total_minutes FROM requests WHERE id=?',[$several])['total_minutes']===120,'Combina horas de varios actos entre varias fechas');
refuses(fn()=>testSubmit(1,'acto',[slot('2026-12-02','07:30','09:30')],[['id'=>$a,'hours'=>'1'],['id'=>$b,'hours'=>'2']]),'La suma seleccionada debe coincidir con el disfrute');
$split=testSubmit(1,'acto',[slot('2026-12-03','07:30','10:30'),slot('2026-12-03','13:00','17:00')],[['id'=>$a,'hours'=>'3'],['id'=>$b,'hours'=>'4']]);
check((int)row('SELECT total_minutes FROM requests WHERE id=?',[$split])['total_minutes']===420,'Admite franjas separadas dentro de la misma fecha');
fixtureOfficialWorker(8);$c=approvedAct(8,'OFICIAL-A');$d=approvedAct(8,'OFICIAL-B');
$worker=testSubmit(8,'acto',[slot('2026-12-02','07:00','15:00')],[['id'=>$c,'hours'=>'3'],['id'=>$d,'hours'=>'5']]);
check((int)row('SELECT total_minutes FROM requests WHERE id=?',[$worker])['total_minutes']===480,'Combina actos en una jornada oficial continua de ocho horas');
refuses(fn()=>testSubmit(8,'acto',[slot('2026-12-03','07:00','15:30')],[['id'=>$c,'hours'=>'8.5']]),'Impide disfrute fuera del horario oficial');
refuses(fn()=>testSubmit(8,'acto',[slot('2026-12-03','07:00','08:00')],[['id'=>$a,'hours'=>'1']]),'Solo selecciona actos propios');
$pending=insert('acts',['user_id'=>8,'number'=>'PENDIENTE','issued_on'=>date('Y-m-d'),'expires_on'=>'2027-12-31','granted_minutes'=>510,'created_at'=>now()]);
refuses(fn()=>testSubmit(8,'acto',[slot('2026-12-03','07:00','08:00')],[['id'=>$pending,'hours'=>'1']]),'No usa actos pendientes de Nómina');
$low=approvedAct(8,'SALDO-BAJO',30);refuses(fn()=>testSubmit(8,'acto',[slot('2026-12-03','07:00','08:00')],[['id'=>$low,'hours'=>'1']]),'No permite sobregirar el saldo de un acto');
$temporary=['name'=>'Extensión temporal','start_date'=>'2026-12-07','end_date'=>'2026-12-07','periods'=>array_fill(1,5,[['06:00','17:00']])];
saveSchedule(person(5),$temporary+['schedule_group'=>'regular']);saveSchedule(person(5),$temporary+['schedule_group'=>'official_worker']);
refuses(fn()=>testSubmit(1,'acto',[slot('2026-12-07','06:00','15:00')],[['id'=>$a,'hours'=>'9']]),'Temporal ampliado no aumenta máximo de 8,5 horas');
refuses(fn()=>testSubmit(8,'acto',[slot('2026-12-07','06:00','14:30')],[['id'=>$c,'hours'=>'8.5']]),'Temporal ampliado no aumenta máximo oficial de ocho horas');
$valid=testSubmit(8,'acto',[slot('2026-12-07','06:00','14:00')],[['id'=>$c,'hours'=>'4'],['id'=>$d,'hours'=>'4']]);
$snapshot=json_decode(row('SELECT snapshot FROM requests WHERE id=?',[$valid])['snapshot'],true);
check($snapshot['act_workdays']['2026-12-07']===[['06:00','17:00']],'Conserva franjas vigentes de la jornada utilizada');
setSetting('official_worker_schedules','[]');$wf->review(person(2),$valid,'approved','');$wf->review(person(3),$valid,'approved','');
check(row('SELECT status FROM requests WHERE id=?',[$valid])['status']==='approved','Cambiar horario después del registro no altera franjas guardadas');
$bad=testSubmit(8,'acto',[slot('2026-12-09','07:00','08:00')],[['id'=>$d,'hours'=>'1']]);
run("UPDATE request_slots SET date='2026-12-10',start_at='2026-12-10 07:00:00',end_at='2026-12-10 08:00:00' WHERE request_id=?",[$bad]);
insert('request_slots',['request_id'=>$bad,'date'=>'2026-12-09','start_time'=>'07:00','end_time'=>'08:00','minutes'=>60,'semester'=>'2026-2','segments'=>'[["07:00","08:00"]]','start_at'=>'2026-12-09 07:00:00','end_at'=>'2026-12-09 08:00:00']);
$result=$wf->approveMany(person(2),[approvalId($bad)]);check(count($result['failed'])===1&&row('SELECT status FROM requests WHERE id=?',[$bad])['status']==='pending','Aprobación masiva rechaza fechas ajenas a la jornada guardada');
refuses(fn()=>testSubmit(1,'acto',[['date'=>'2026-12-16','mode'=>'full']],[['id'=>$a,'hours'=>'8.5']]),'Servidor bloquea día completo para actos administrativos');
$long=testSubmit(8,'acto',[slot('2026-12-17','07:00','15:00'),slot('2026-12-18','07:00','15:00')],[['id'=>$c,'hours'=>'8'],['id'=>$d,'hours'=>'8']]);
check((int)row('SELECT total_minutes FROM requests WHERE id=?',[$long])['total_minutes']===960,'Permite 16 horas totales oficiales distribuidas en dos jornadas de ocho');
$bulk=$wf->approveMany(person(2),[approvalId($long)]);check(count($bulk['failed'])===0,'Aprobación masiva permite varias fechas válidas');$wf->review(person(3),$long,'approved','');
$one=testSubmit(1,'acto',[slot('2026-12-21','07:30','13:30'),slot('2026-12-22','07:30','13:30')],[['id'=>$a,'hours'=>'10']]);
check((int)row('SELECT minutes FROM allocations WHERE request_id=?',[$one])['minutes']===600,'Un único acto puede aportar más de una jornada entre varias fechas');
run("UPDATE requests SET status='returned' WHERE id=?",[$worker]);
$expectedAllocations=[$c=>3,$d=>5];$_SESSION=['csrf'=>'token-aislado'];
$u=person(8);$_SERVER['REQUEST_METHOD']='GET';$_GET=['id'=>$worker];$_POST=[];
function icon(string $name):string{return '';}
approvedAct(8,'ACTO-ADICIONAL');
ob_start();require ROOT.'/views/new.php';$html=ob_get_clean();
$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);$xpath=new DOMXPath($dom);
foreach($expectedAllocations as $actId=>$hours){
 $nodes=$xpath->query('//div[contains(@class,"allocation-row")][input[@type="hidden" and @value="'.$actId.'"]]//input[@data-allocation-hours]');
 check($nodes->length===1&&(float)$nodes->item(0)->getAttribute('value')===$hours*1.0,'Corrección recupera horas por identificador del acto '.$actId);
}
check(str_contains($html,'data-daily-limit="480"')&&str_contains($html,'id="allocation-summary"'),'Formulario oficial muestra máximo diario y resumen de horas');
echo $checks." comprobaciones correctas, sin envíos reales.\n";
