<?php
declare(strict_types=1);
require __DIR__.'/legalizations.php';require ROOT.'/app/LaborRecords.php';
$clock=new DateTimeImmutable('2026-11-27 18:00:00');
$beforeApproved=analyticsData(['metric'=>'approved','year'=>'2026'],$clock)['counts'];
$beforeEnjoyed=analyticsData(['metric'=>'enjoyed','year'=>'2026'],$clock)['counts'];
foreach(HIDDEN_LICENSE_SLUGS as $slug){
 $category=row('SELECT * FROM categories WHERE slug=?',[$slug]);
 $id=insert('requests',['user_id'=>1,'category_id'=>$category['id'],'status'=>'approved','justification'=>'Expediente histórico de prueba','total_minutes'=>60,'snapshot'=>'{}','created_at'=>now(),'updated_at'=>now()]);
 insert('request_slots',['request_id'=>$id,'date'=>'2026-11-26','start_time'=>'07:30','end_time'=>'08:30','start_at'=>'2026-11-26 07:30:00','end_at'=>'2026-11-26 08:30:00','minutes'=>60,'segments'=>'[["07:30","08:30"]]','semester'=>'2026-2','is_full_day'=>0]);
 insert('approvals',['request_id'=>$id,'revision'=>1,'step'=>1,'role'=>'subsecretary','assigned_user_id'=>3,'status'=>'approved','acted_by'=>3,'acted_at'=>now()]);
 $r=row('SELECT * FROM requests WHERE id=?',[$id]);
 ck(!canReadRequest(lp(1),$r),'Oculta detalle y descargas de '.$slug);
 no(fn()=>$wf->submit(lp(1),['category_id'=>$category['id']],[]),'Bloquea nueva solicitud oculta '.$slug);
 no(fn()=>$wf->cancel(lp(1),$id,'Prueba de acceso directo'),'Bloquea operación directa sobre '.$slug);
 ck(row('SELECT id FROM requests WHERE id=?',[$id])!==null,'Conserva expediente histórico '.$slug);
}
ck(analyticsData(['metric'=>'approved','year'=>'2026'],$clock)['counts']===$beforeApproved,'Tablero aprobado excluye licencias ocultas');
ck(analyticsData(['metric'=>'enjoyed','year'=>'2026'],$clock)['counts']===$beforeEnjoyed,'Tablero disfrutado excluye licencias ocultas');
$visible=rows('SELECT slug FROM categories WHERE '.visibleCategorySql('')." AND slug!='bici'");$slugs=array_column($visible,'slug');
ck(!array_intersect(HIDDEN_LICENSE_SLUGS,$slugs) && in_array('luto',$slugs,true) && in_array('matrimonio',$slugs,true),'Catálogo conserva Luto y Matrimonio sin tipos ocultos');
$records=laborRecordQuery(['from'=>'2026-01-01','to'=>'2027-12-31','person'=>''])->fetchAll();
ck(!array_intersect(array_column($records,'category_id'),array_column(rows("SELECT id FROM categories WHERE NOT (".visibleCategorySql('').")"),'id')),'Historias Laborales excluye expedientes ocultos');
ck((int)row("SELECT COUNT(*) n FROM categories WHERE NOT (".visibleCategorySql('').")")['n']===3,'No elimina categorías de la base de datos');
echo "Exclusión de licencias verificada.\n";
