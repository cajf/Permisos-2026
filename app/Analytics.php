<?php
declare(strict_types=1);

function canAccessAnalytics(array $actor): bool {
    $current=row('SELECT * FROM users WHERE id=? AND active=1',[$actor['id']??0]);
    if(!$current || !hasRole($current,'subsecretary')) return false;
    $assignment=designation('subsecretary');
    $principal=$assignment?(int)$assignment['user_id']:(int)setting('subsecretary_id');
    return $principal===(int)$current['id'];
}
function authorizeAnalytics(array $actor): void {
    ensure(canAccessAnalytics($actor),'El tablero está disponible únicamente para el subsecretario administrativo del talento humano titular vigente.');
}
/** Una fila por solicitud y vigencia con tiempo real de disfrute positivo. Sin datos personales. */
function enjoyedFacts(?DateTimeImmutable $clock=null): array {
    $clock??=new DateTimeImmutable();$facts=[];
    $sql="SELECT r.id,r.status,r.cancelled_at,r.department_id,c.family,c.name category,d.name department,s.date,s.segments FROM requests r JOIN categories c ON c.id=r.category_id JOIN request_slots s ON s.request_id=r.id LEFT JOIN departments d ON d.id=r.department_id WHERE r.status IN ('approved','cancel_pending','cancelled') AND s.date<=? AND EXISTS(SELECT 1 FROM approvals a WHERE a.request_id=r.id AND a.revision=r.revision AND a.status='approved' AND a.step=(SELECT MAX(b.step) FROM approvals b WHERE b.request_id=r.id AND b.revision=r.revision)) ORDER BY r.id,s.date,s.start_time";
    foreach(rows($sql,[$clock->format('Y-m-d')]) as $slot) {
        $cap=$clock;
        if($slot['status']==='cancelled') {
            // Sin fecha efectiva no se inventa disfrute histórico de una cancelación.
            if(!$slot['cancelled_at']) continue;
            $cap=min($cap,new DateTimeImmutable($slot['cancelled_at']));
        }
        foreach(json_decode($slot['segments'],true,512,JSON_THROW_ON_ERROR) as [$a,$b]) {
            $from=new DateTimeImmutable(strlen($a)>5?$a:$slot['date'].' '.$a);
            $to=min(new DateTimeImmutable(strlen($b)>5?$b:$slot['date'].' '.$b),$cap);
            while($from<$to) {
                $year=(int)$from->format('Y');$next=min($to,new DateTimeImmutable(($year+1).'-01-01 00:00:00'));
                $key=$slot['id'].'-'.$year;
                $facts[$key]??=['request_id'=>(int)$slot['id'],'year'=>$year,'department_id'=>(int)($slot['department_id']??0),'department'=>$slot['department']??'Sin dependencia histórica','family'=>$slot['family'],'category'=>$slot['category'],'seconds'=>0];
                $facts[$key]['seconds']+=$next->getTimestamp()-$from->getTimestamp();$from=$next;
            }
        }
    }
    return array_values($facts);
}
function analyticsSelection(array $input): array {
    $year=(string)($input['year']??date('Y'));$department=(string)($input['department']??'all');
    ensure($year==='all' || preg_match('/^(19|20|21)\d{2}$/D',$year),'Seleccione una vigencia válida.');
    ensure($department==='all' || preg_match('/^\d{1,18}$/D',$department),'Seleccione una dependencia válida.');
    return [$year,$department];
}
function analyticsData(array $input,?DateTimeImmutable $clock=null): array {
    [$year,$department]=analyticsSelection($input);$clock??=new DateTimeImmutable();$facts=enjoyedFacts($clock);
    $counts=['total'=>[],'permiso'=>[],'compensatorio'=>[],'licencia'=>[]];$byDepartment=[];$byYear=[];$years=[(int)$clock->format('Y')];$departments=[];
    foreach(rows('SELECT id,name FROM departments ORDER BY name') as $d) $departments[(int)$d['id']]=$d['name'];
    foreach($facts as $f) {
        $years[]=$f['year'];$departments[$f['department_id']]??=$f['department'];
        $family=in_array($f['family'],['remunerada','no_remunerada'],true)?'licencia':$f['family'];
        if(!isset($counts[$family])) continue;
        if($department!=='all' && $f['department_id']!==(int)$department) continue;
        $byYear[$f['year']]??=['year'=>$f['year'],'permiso'=>0,'compensatorio'=>0,'licencia'=>0];
        $byYear[$f['year']][$family]++;
        if($year!=='all' && $f['year']!==(int)$year) continue;
        $id=$f['request_id'];$counts['total'][$id]=true;$counts[$family][$id]=true;
        $key=$f['department_id'];
        $byDepartment[$key]??=['id'=>$key,'name'=>$f['department'],'permiso'=>[],'compensatorio'=>[],'licencia'=>[],'total'=>[]];
        $byDepartment[$key][$family][$id]=true;$byDepartment[$key]['total'][$id]=true;
    }
    foreach($counts as &$set)$set=count($set);unset($set);
    foreach($byDepartment as &$d)foreach(['permiso','compensatorio','licencia','total'] as $k)$d[$k]=count($d[$k]);unset($d);
    // Incluye dependencias sin disfrute para evitar confundir ausencia con información oculta.
    foreach($departments as $id=>$name) if(($department==='all'||$id===(int)$department)&&!isset($byDepartment[$id]))$byDepartment[$id]=['id'=>$id,'name'=>$name,'permiso'=>0,'compensatorio'=>0,'licencia'=>0,'total'=>0];
    usort($byDepartment,fn($a,$b)=>$b['total']<=>$a['total']?:strcmp($a['name'],$b['name']));ksort($byYear);$years=array_values(array_unique($years));rsort($years);
    return ['generated_at'=>$clock->format('Y-m-d H:i:s'),'timezone'=>'America/Bogota','year'=>$year,'department'=>$department,'counts'=>$counts,'departments'=>array_values($byDepartment),'trend'=>array_values($byYear),'years'=>$years,'department_options'=>array_map(fn($id,$name)=>['id'=>$id,'name'=>$name],array_keys($departments),array_values($departments))];
}
