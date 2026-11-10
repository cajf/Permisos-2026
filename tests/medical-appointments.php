<?php
declare(strict_types=1);
putenv('APP_TEST_ATTACHMENT_STORAGE=mysql');
require __DIR__.'/mail-run.php';

$category=cat('ordinario');
run('UPDATE categories SET supports_required=0 WHERE id=?',[$category['id']]);
run("UPDATE requests SET status='rejected' WHERE category_id=? AND user_id IN (1,8)",[$category['id']]);
fixtureOfficialWorker(1);
$clock=new DateTimeImmutable('2026-11-09 06:00:00');
$image=tempnam(sys_get_temp_dir(),'th-cita-');
file_put_contents($image,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aBP8AAAAASUVORK5CYII='));
$support=['tmp'=>$image,'original_name'=>'cita.png','mime'=>'image/png','size'=>filesize($image),'sha256'=>hash_file('sha256',$image)];
try {
    foreach ([1=>'07:00',8=>'07:30'] as $userId=>$start) {
        $person=person($userId);
        $limit=ordinaryQuotaLimit($person);
        $base=['category_id'=>$category['id'],'justification'=>'Prueba del permiso Ordinario-Remunerado'];
        $regular=$base+['medical_appointment'=>'0','slots'=>array_map(fn($date)=>['date'=>$date,'mode'=>'full'],['2026-12-01','2026-12-02','2026-12-03'])];
        $wf->submit($person,$regular,[],null,$clock);
        check($wf->quota($userId,'2026-2')===$limit,'El permiso no médico consume el cupo de '.$person['name']);
        $appointment=$base+['slots'=>[slot('2026-12-04',$start,'08:30')]];
        refuses(fn()=>$wf->submit($person,$appointment,[$support],null,$clock),'Exige respuesta explícita sobre la cita');
        refuses(fn()=>$wf->submit($person,$appointment+['medical_appointment'=>'0'],[],null,$clock),'Bloquea permisos no médicos con cupo agotado');
        $appointment['medical_appointment']='1';
        refuses(fn()=>$wf->submit($person,$appointment,[],null,$clock),'Exige soporte médico con el catálogo en opcional');
        $id=$wf->submit($person,$appointment,[$support],null,$clock);
        check($wf->quota($userId,'2026-2')===$limit,'La cita pendiente queda fuera del cupo de '.$person['name']);
        $wf->review(person(2),$id,'returned','Ajuste la justificación de prueba.');
        $wf->submit($person,$appointment,[],$id,$clock);
        check((int)row('SELECT COUNT(*) n FROM attachments WHERE request_id=?',[$id])['n']===1,'La corrección reutiliza el soporte ya adjunto');
        $batch=$wf->approveMany(person(2),[approvalId($id)]);
        check($batch['approved']===[$id] && row('SELECT status FROM requests WHERE id=?',[$id])['status']==='approved','La aprobación masiva admite la cita con cupo agotado');
        $balance=json_decode(row('SELECT data FROM balance_snapshots WHERE user_id=? ORDER BY id DESC LIMIT 1',[$userId])['data'],true);
        check($balance['quotas']['2026-2']===0 && $balance['quota_limit']===$limit && $wf->quota($userId,'2026-2')===$limit,'La aprobación no descuenta la cita del saldo semestral');
    }
    $appointment['slots']=[slot('2026-12-07','07:30','08:30')];
    $id=$wf->submit(person(8),$appointment,[$support],null,$clock);
    run('DELETE FROM attachments WHERE request_id=?',[$id]);
    refuses(fn()=>$wf->review(person(2),$id,'approved',''),'La aprobación individual bloquea una cita sin soporte');
    $batch=$wf->approveMany(person(2),[approvalId($id)]);
    check($batch['approved']===[] && str_contains($batch['failed'][0]['message'],'soporte obligatorio'),'La aprobación masiva informa el soporte faltante');
} finally {
    unlink($image);
}
echo $checks." comprobaciones correctas, sin envíos reales.\n";
