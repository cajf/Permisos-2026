<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';require __DIR__.'/../app/bootstrap.php';
installMysqlSchema(db());installLegalizationSchema(db());
insert('departments',['name'=>'Prueba legalización']);
foreach([1=>'[]',2=>'["manager"]',3=>'["subsecretary"]',4=>'["admin"]'] as $id=>$roles)fixtureUser(['name'=>'Prueba '.$id,'identification'=>'LEG-'.$id,'username'=>'legal'.$id,'email'=>'legal'.$id.'@ejemplo.invalid','password'=>password_hash('Temporal1234',PASSWORD_DEFAULT),'job_title'=>'Prueba','department_id'=>1,'manager_id'=>null,'roles'=>$roles,'force_password'=>0]);
run('UPDATE person_employments SET manager_id=2');setSetting('subsecretary_id','3');
foreach(require ROOT.'/database/catalog.php' as $c)insert('categories',array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$c));
run('UPDATE categories SET supports_required=0');
function lp(int $id):array{return row('SELECT * FROM user_profiles WHERE id=?',[$id]);}
$checks=0;
function ck(bool $ok,string $text):void {global $checks;ensure($ok,$text);$checks++;echo "OK $text\n";}
function no(callable $f,string $text):void {try{$f();}catch(DomainException){ck(true,$text);return;}throw new RuntimeException($text);}
$wf=new Workflow();
foreach(['luto'=>'2026-12-01','matrimonio'=>'2026-12-15'] as $slug=>$date){
 $id=$wf->submit(lp(1),['category_id'=>row('SELECT id FROM categories WHERE slug=?',[$slug])['id'],'slots'=>[['date'=>$date,'mode'=>'full']],'relationship'=>'Padre','justification'=>'Prueba de legalización','exception_reason'=>'Prueba justificada'],[],null,new DateTimeImmutable('2026-11-27 06:00:00'));
 no(fn()=>saveLegalization(lp(4),$id,true,0),'No legaliza licencia pendiente '.$slug);
 $wf->review(lp(2),$id,'approved','');
 $mail=row('SELECT body FROM outbox WHERE recipient=? ORDER BY id DESC LIMIT 1',[lp(1)['email']]);
 ck(!str_contains($mail['body'],'legalizar'),'Aprobación intermedia no informa legalización');
 $wf->review(lp(3),$id,'approved','');
 $mail=row('SELECT body FROM outbox WHERE recipient=? ORDER BY id DESC LIMIT 1',[lp(1)['email']]);
 $text=json_decode($mail['body'],true)['text'];
 ck(str_contains($text,'legalizar') && str_contains($text,'Talento Humano'),'Correo final informa legalización '.$slug);
 ck($slug==='luto'?str_contains($text,'30 días hábiles'):!str_contains($text,'30 días'),'Plazo correcto según licencia '.$slug);
 no(fn()=>saveLegalization(lp(1),$id,true,0),'Funcionario no puede legalizar');
 no(fn()=>saveLegalization(lp(3),$id,true,0),'Subsecretario no puede legalizar sin rol TH');
 saveLegalization(lp(4),$id,true,0);
 ck((int)row('SELECT legalized FROM request_legalizations WHERE request_id=?',[$id])['legalized']===1,'Checkbox marcado guarda Legalizada');
 $count=(int)row('SELECT COUNT(*) n FROM events WHERE request_id=?',[$id])['n'];saveLegalization(lp(4),$id,true,1);
 ck((int)row('SELECT COUNT(*) n FROM events WHERE request_id=?',[$id])['n']===$count,'Guardar sin cambio no duplica historial');
 no(fn()=>saveLegalization(lp(4),$id,false,0),'No sobrescribe una versión desactualizada');
 saveLegalization(lp(4),$id,false,1);
 ck((int)row('SELECT legalized FROM request_legalizations WHERE request_id=?',[$id])['legalized']===0,'Permite desmarcar sin motivo ni anexos');
 ck(row('SELECT status FROM requests WHERE id=?',[$id])['status']==='approved','Marcación no modifica aprobación');
 ck((int)row("SELECT COUNT(*) n FROM events WHERE request_id=? AND action IN ('Licencia legalizada','Legalización desmarcada')",[$id])['n']===2,'Audita responsable y cambios');
 $last=$id;
}
$u=lp(4);$_GET=[];$_SESSION['csrf']='test-legalization-token';
ob_start();require ROOT.'/views/legalizations.php';$html=ob_get_clean();
ck(substr_count($html,'name="legalized"')===2 && str_contains($html,'name="csrf"'),'Vista muestra checkbox protegido para las dos pendientes');
ck(str_contains($html,'Página 1 de 1') && str_contains($html,'value="legalized"'),'Vista muestra paginación y filtro de legalización');
run("UPDATE requests SET status='cancelled' WHERE id=?",[$last]);no(fn()=>saveLegalization(lp(4),$last,true,2),'No modifica licencia cancelada');
run('UPDATE users SET active=0 WHERE id=4');no(fn()=>saveLegalization(lp(4),$last,true,2),'Cuenta inactiva no puede marcar');
ck(legalizationNotice('ordinario')==='','Otros tipos no muestran aviso');
echo "$checks comprobaciones de legalización correctas, sin enviar correos reales.\n";
