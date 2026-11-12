<?php
declare(strict_types=1);

function listingText(array $input,string $key): string {
    $value=$input[$key]??'';ensure(is_string($value)&&mb_strlen($value)<=180,'El filtro no es válido.');return trim($value);
}
function listingLike(string $value): string {return '%'.str_replace(['=','%','_'],['==','=%','=_'],$value).'%';}
function listingMatch(array &$where,array &$params,string $column,string $value): void {
    if($value==='')return;
    $where[]="$column COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '='";$params[]=listingLike($value);
}
function listingRows(string $sql,array $params,array $input,int $pageSize=10): array {
    $page=max(1,min(100000,(int)($input['list_page']??1)));
    $items=rows($sql.' LIMIT '.($pageSize+1).' OFFSET '.(($page-1)*$pageSize),$params);
    return ['items'=>array_slice($items,0,$pageSize),'more'=>count($items)>$pageSize,'page'=>$page,'page_size'=>$pageSize];
}

function searchActivityHistory(array $actor,array $input): array {
    requirePermission($actor,'view.audit');
    $from=listingText($input,'q_from');
    $to=listingText($input,'q_to');
    ensure(($from==='' || validDate($from)) && ($to==='' || validDate($to))
        && ($from==='' || $to==='' || $from<=$to),'Seleccione un rango de fechas válido.');

    $where=['1=1'];
    $params=[];
    if($from!=='') {
        $where[]='e.created_at>=?';
        $params[]=$from.' 00:00:00';
    }
    if($to!=='') {
        $where[]='e.created_at<=?';
        $params[]=$to.' 23:59:59';
    }
    listingMatch($where,$params,'p.name',listingText($input,'q_responsible'));
    return listingRows('SELECT e.*,p.name FROM events e JOIN persons p ON p.id=e.actor_id WHERE '
        .implode(' AND ',$where).' ORDER BY e.created_at DESC,e.id DESC',$params,$input,50);
}
function listingRoles(): array {return ['superadmin'=>'Superadministrador','employee'=>'Funcionario sin roles adicionales','admin'=>'Administrador de Talento Humano','manager'=>'Jefe inmediato','subsecretary'=>'Subsecretario','mayor'=>'Alcalde','payroll'=>'Nómina'];}
function staffDirectorySource(): string {
    return "SELECT p.*,e.department_id,e.employment_type AS appointment_type,e.high_rank,
        j.title AS job_title,j.code,COALESCE(e.grade,'') AS grade,COALESCE(c.name,'') AS job_category,
        a.username,a.active,COALESCE(a.roles,JSON_ARRAY()) AS roles
        FROM persons p JOIN person_employments e ON e.person_id=p.id
        JOIN jobs j ON j.id=e.job_id LEFT JOIN official_job_categories c ON c.id=j.official_category_id
        LEFT JOIN user_profiles a ON a.id=p.id";
}

function searchUserDirectory(array $actor,array $input): array {
    requirePermission($actor,'view.users');$where=['1=1'];$params=[];
    foreach(['q_name'=>'u.name','q_username'=>'u.username','q_identification'=>'u.identification'] as $key=>$column)listingMatch($where,$params,$column,listingText($input,$key));
    $job=listingText($input,'q_job');if($job!==''){$where[]='u.job_title=?';$params[]=$job;}
    $role=listingText($input,'q_role');if($role!==''){
        ensure(isset(listingRoles()[$role]),'Seleccione un rol válido.');
        if($role==='employee')$where[]='u.username IS NOT NULL AND NOT EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id=u.id)';
        else{$where[]='EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id=u.id AND ur.role_code=?)';$params[]=$role;}
    }
    return listingRows('SELECT u.*,d.name department FROM ('.staffDirectorySource().') u JOIN departments d ON d.id=u.department_id WHERE '.implode(' AND ',$where).' ORDER BY u.name,u.id',$params,$input);
}
function searchJobDirectory(array $actor,array $input): array {
    requirePermission($actor,'view.jobs');$where=['1=1'];$params=[];
    foreach(['q_job'=>'j.title','q_code'=>'j.code'] as $key=>$column)listingMatch($where,$params,$column,listingText($input,$key));
    $grade=listingText($input,'q_grade');if($grade!==''){$where[]="EXISTS(SELECT 1 FROM job_grades g WHERE g.job_id=j.id AND g.grade COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '=')";$params[]=listingLike($grade);}
    $group=listingText($input,'q_group');if($group!==''){ensure(in_array($group,['regular','official_worker'],true),'Grupo no válido.');$where[]='j.employment_group=?';$params[]=$group;}
    $category=listingText($input,'q_category');listingMatch($where,$params,'c.name',$category);
    return listingRows('SELECT j.*,c.name job_category FROM jobs j LEFT JOIN official_job_categories c ON c.id=j.official_category_id WHERE '.implode(' AND ',$where).' ORDER BY j.title,j.id',$params,$input);
}
function searchDesignationHistory(array $actor,array $input): array {
    requirePermission($actor,'view.designations');$where=['1=1'];$params=[];$role=listingText($input,'q_role');
    if($role!==''){ensure(in_array($role,['manager','subsecretary'],true),'Seleccione un rol de designación válido.');$where[]='a.role=?';$params[]=$role;}
    $name=listingText($input,'q_name');if($name!==''){$where[]="(u.name COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '=' OR v.name COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '=')";array_push($params,listingLike($name),listingLike($name));}
    $from=listingText($input,'q_from');$to=listingText($input,'q_to');
    ensure(($from===''||validDate($from))&&($to===''||validDate($to))&&($from===''||$to===''||$from<=$to),'Seleccione un intervalo de vigencia válido.');
    if($from!==''){$where[]='(a.end_date IS NULL OR a.end_date>=?)';$params[]=$from;}
    if($to!==''){$where[]='a.start_date<=?';$params[]=$to;}
    return listingRows('SELECT a.*,u.name principal,v.name alternate,d.name department,c.name creator FROM designations a JOIN user_profiles u ON u.id=a.user_id LEFT JOIN user_profiles v ON v.id=a.alternate_id LEFT JOIN departments d ON d.id=a.department_id JOIN user_profiles c ON c.id=a.created_by WHERE '.implode(' AND ',$where).' ORDER BY a.start_date DESC,a.id DESC',$params,$input);
}
function searchRegisteredActs(array $actor,array $input,?string $status=null): array {
    $institutional=can($actor,'data.acts_all');$where=['1=1'];$params=[];
    if(!$institutional){$where[]='a.user_id=?';$params[]=$actor['id'];}
    if($status!==null){$where[]='a.status=?';$params[]=$status;}
    listingMatch($where,$params,'a.number',listingText($input,'q_number'));
    if($institutional)foreach(['q_name'=>'u.name','q_identification'=>'u.identification'] as $key=>$column)listingMatch($where,$params,$column,listingText($input,$key));
    $base=' FROM acts a JOIN user_profiles u ON u.id=a.user_id WHERE '.implode(' AND ',$where);
    // Cada página contiene hasta diez resoluciones; todos sus registros coincidentes permanecen juntos.
    $listing=listingRows('SELECT a.number,MAX(a.id) latest_id,COUNT(*) records'.$base.' GROUP BY a.number ORDER BY latest_id DESC,a.number',$params,$input);
    $numbers=array_column($listing['items'],'number');$groups=[];
    if($numbers){$records=rows('SELECT a.*,u.name'.$base.' AND a.number IN ('.implode(',',array_fill(0,count($numbers),'?')).') ORDER BY a.id DESC',[...$params,...$numbers]);foreach($records as $record)$groups['n:'.$record['number']][]=$record;}
    foreach($listing['items'] as &$group)$group['acts']=$groups['n:'.$group['number']]??[];unset($group);
    return $listing;
}
