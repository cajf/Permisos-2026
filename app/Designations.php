<?php
declare(strict_types=1);

function designation(string $role,?int $department=null,?string $date=null): ?array {
    $date??=date('Y-m-d');
    return row('SELECT * FROM designations WHERE role=? AND COALESCE(department_id,0)=CAST(? AS SIGNED) AND start_date<=? AND (end_date IS NULL OR end_date>=?) ORDER BY start_date DESC LIMIT 1',[$role,$department??0,$date,$date]);
}
function saveDesignation(array $actor,array $data): void {
    authorizeAdmin($actor);
    $role=$data['role']??'';$start=$data['start_date']??'';
    ensure(in_array($role,['manager','subsecretary'],true),'Seleccione el tipo de designación.');
    ensure(validDate($start) && $start>=date('Y-m-d'),'La nueva designación debe comenzar hoy o en una fecha futura.');
    $principal=(int)($data['user_id']??0);$alternate=(int)($data['alternate_id']??0);
    foreach ([$principal,$alternate] as $i=>$id) {
        if (!$id && $i===1) continue;
        $person=row('SELECT * FROM users WHERE id=? AND active=1',[$id]);
        ensure($person!==null && hasRole($person,$role),'El responsable y su suplente deben estar activos y tener el rol correspondiente.');
    }
    ensure(!$alternate || $alternate!==$principal,'El responsable y el suplente deben ser diferentes.');
    $departments=$role==='manager'?($data['department_ids']??[]):[null];
    ensure(is_array($departments) && count($departments)>0 && count($departments)<=500,'Seleccione al menos una dependencia.');
    atomic(function() use($actor,$role,$start,$principal,$alternate,$departments) {
        foreach (array_unique($departments,SORT_REGULAR) as $department) {
            $department=$role==='manager'?(int)$department:null;
            if ($role==='manager') ensure(row('SELECT id FROM departments WHERE id=?',[$department])!==null,'Dependencia no válida.');
            $last=row('SELECT * FROM designations WHERE role=? AND COALESCE(department_id,0)=CAST(? AS SIGNED) ORDER BY start_date DESC LIMIT 1',[$role,$department??0]);
            ensure(!$last || $start>$last['start_date'],'La fecha debe ser posterior al inicio de la última designación registrada.');
            if ($last) run('UPDATE designations SET end_date=? WHERE id=?',[(new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d'),$last['id']]);
            $id=insert('designations',['role'=>$role,'department_id'=>$department,'user_id'=>$principal,'alternate_id'=>$alternate?:null,'start_date'=>$start,'created_by'=>$actor['id'],'created_at'=>now()]);
            event((int)$actor['id'],'Designación registrada',roleLabel($role).' · Designación '.$id.' · Dependencia '.($department??'Institucional').' · Responsable '.$principal.' · Suplente '.($alternate?:'Sin asignar').' · Inicio '.$start.($last?' · Cierre anterior '.(new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d'):''));
        }
        notify($principal,'Nueva designación de aprobación','Su designación comienza el '.$start.'. Los trámites anteriores permanecen con el responsable que los recibió.');
    });
}

/** Instantáneas inmutables: permiten consultar saldos sin revelar cambios posteriores al periodo autorizado. */
function captureBalances(int $user): void {
    $person=row('SELECT * FROM users WHERE id=?',[$user]);if (!$person) return;
    $wf=new Workflow();$types=[];
    foreach (rows('SELECT * FROM categories ORDER BY id') as $category) {
        $reserved=0;$used=0;$benefits=[];
        foreach (rows('SELECT r.*,s.date,s.start_time,s.end_time,s.minutes,s.segments FROM requests r JOIN request_slots s ON s.request_id=r.id WHERE r.user_id=? AND r.category_id=?',[$user,$category['id']]) as $slot) {
            if (in_array($slot['status'],['pending','returned'],true)) $reserved+=(int)$slot['minutes'];
            if (in_array($slot['status'],['approved','cancel_pending','cancelled'],true)) $used+=$wf->consumedSlot($slot,$slot['status']==='cancelled'?$slot['cancelled_at']:null);
            if ($slot['birthday_year'] && !in_array($slot['status'],['rejected','cancelled'],true)) $benefits[]=(int)$slot['birthday_year'];
            if ($slot['birthday_year'] && $slot['status']==='cancelled' && $wf->consumedSlot($slot,$slot['cancelled_at'])>0) $benefits[]=(int)$slot['birthday_year'];
        }
        $types[]=['name'=>$category['name'],'family'=>$category['family'],'reserved'=>$reserved,'used'=>$used,'birthday_years'=>array_values(array_unique($benefits))];
    }
    $quotas=[];
    foreach (rows("SELECT s.* FROM request_slots s JOIN requests r ON r.id=s.request_id JOIN categories c ON c.id=r.category_id WHERE r.user_id=? AND c.slug='ordinario'",[$user]) as $slot) foreach (array_keys($wf->slotSemesters($slot)) as $semester) $quotas[$semester]=max(0,1530-$wf->quota($user,$semester));
    $current=date('Y').'-'.((int)date('m')<=6?'1':'2');$quotas[$current]??=1530;
    $acts=[];foreach (rows("SELECT * FROM acts WHERE user_id=? AND status='approved'",[$user]) as $act) $acts[]=['number'=>$act['number'],'granted'=>(int)$act['granted_minutes'],'available'=>$wf->available($act),'expires_on'=>$act['expires_on']];
    insert('balance_snapshots',['user_id'=>$user,'department_id'=>$person['department_id'],'created_at'=>now(),'data'=>json_encode(['name'=>$person['name'],'types'=>$types,'quotas'=>$quotas,'acts'=>$acts],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}
function balanceScope(array $viewer,string $date): array {
    ensure(validDate($date) && $date<=date('Y-m-d'),'Seleccione una fecha de corte válida, hasta hoy.');
    if (hasRole($viewer,'admin')) return ['all'=>true,'departments'=>[]];
    $departments=array_map('intval',array_column(rows("SELECT DISTINCT department_id FROM designations WHERE role='manager' AND (user_id=? OR alternate_id=?) AND start_date<=? AND (end_date IS NULL OR end_date>=?)",[$viewer['id'],$viewer['id'],$date,$date]),'department_id'));
    return ['all'=>false,'departments'=>$departments];
}
/** Consulta explícita y paginada; filtra en MySQL antes de cargar los saldos. */
function searchBalanceSnapshots(array $viewer,array $input): array {
    $from=trim((string)($input['from']??''));$to=trim((string)($input['to']??''));
    ensure(validDate($from)&&validDate($to)&&$from<=$to&&$to<=date('Y-m-d'),'Seleccione fechas válidas: inicio no posterior al final y final hasta hoy.');
    ensure(substr($from,0,4)===substr($to,0,4),'La fecha de inicio y la fecha final deben pertenecer al mismo año.');
    $name=trim((string)($input['name']??''));ensure(mb_strlen($name)<=120,'El nombre no puede superar 120 caracteres.');
    $department=(string)($input['department']??'all');ensure($department==='all'||preg_match('/^[1-9][0-9]{0,17}$/D',$department),'Seleccione una dependencia válida.');
    $scope=balanceScope($viewer,$to);$where=['s.created_at>=?','s.created_at<=?'];$params=[$from.' 00:00:00',$to.' 23:59:59'];
    if(!$scope['all']) {
        // Las consultas históricas respetan el periodo del jefe y de cada registro.
        $where[]="(s.user_id=? OR (s.department_id IN (".implode(',',array_fill(0,count($scope['departments'])?:1,'?')).") AND EXISTS(SELECT 1 FROM designations dg WHERE dg.role='manager' AND dg.department_id=s.department_id AND (dg.user_id=? OR dg.alternate_id=?) AND dg.start_date<=DATE(s.created_at) AND (dg.end_date IS NULL OR dg.end_date>=DATE(s.created_at)))))";
        array_push($params,$viewer['id'],...($scope['departments']?:[0]));array_push($params,$viewer['id'],$viewer['id']);
    }
    if($department!=='all'){$where[]='s.department_id=?';$params[]=$department;}
    if($name!==''){$where[]="JSON_UNQUOTE(JSON_EXTRACT(s.data,'$.name')) COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '='";$params[]='%'.str_replace(['=','%','_'],['==','=%','=_'],$name).'%';}
    // Una ficha por funcionario: último saldo registrado dentro del intervalo.
    $where[]='NOT EXISTS(SELECT 1 FROM balance_snapshots newer WHERE newer.user_id=s.user_id AND newer.created_at<=? AND (newer.created_at>s.created_at OR (newer.created_at=s.created_at AND newer.id>s.id)))';$params[]=$to.' 23:59:59';
    $page=max(1,min(100000,(int)($input['results_page']??1)));$offset=($page-1)*20;
    $items=rows('SELECT s.*,d.name department FROM balance_snapshots s JOIN departments d ON d.id=s.department_id WHERE '.implode(' AND ',$where).' ORDER BY s.user_id LIMIT 21 OFFSET '.$offset,$params);
    $more=count($items)>20;return ['items'=>array_slice($items,0,20),'more'=>$more,'page'=>$page];
}
function initializeBalanceHistory(): void {
    if (!tableExists('balance_snapshots') || setting('balance_history_started')!=='') return;
    atomic(function() {
        foreach (rows('SELECT id FROM users') as $person) captureBalances((int)$person['id']);
        setSetting('balance_history_started',now());
    });
}
