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
function listingRows(string $sql,array $params,array $input): array {
    $page=max(1,min(100000,(int)($input['list_page']??1)));
    $items=rows($sql.' LIMIT 11 OFFSET '.(($page-1)*10),$params);
    return ['items'=>array_slice($items,0,10),'more'=>count($items)>10,'page'=>$page];
}
function listingRoles(): array {return ['employee'=>'Funcionario sin roles adicionales','admin'=>'Administrador de Talento Humano','manager'=>'Jefe inmediato','subsecretary'=>'Subsecretario','mayor'=>'Alcalde','payroll'=>'Nómina'];}
function searchUserDirectory(array $actor,array $input): array {
    authorizeAdmin($actor);$where=['1=1'];$params=[];
    foreach(['q_name'=>'u.name','q_username'=>'u.username','q_identification'=>'u.identification'] as $key=>$column)listingMatch($where,$params,$column,listingText($input,$key));
    $job=listingText($input,'q_job');if($job!==''){$where[]='u.job_title=?';$params[]=$job;}
    $role=listingText($input,'q_role');if($role!==''){
        ensure(isset(listingRoles()[$role]),'Seleccione un rol válido.');
        if($role==='employee')$where[]='JSON_LENGTH(u.roles)=0';else{$where[]='JSON_CONTAINS(u.roles,?)';$params[]=json_encode($role);}
    }
    return listingRows('SELECT u.*,d.name department FROM users u JOIN departments d ON d.id=u.department_id WHERE '.implode(' AND ',$where).' ORDER BY u.name,u.id',$params,$input);
}
function searchJobDirectory(array $actor,array $input): array {
    authorizeAdmin($actor);$where=['1=1'];$params=[];
    foreach(['q_job'=>'j.title','q_code'=>'j.code'] as $key=>$column)listingMatch($where,$params,$column,listingText($input,$key));
    $grade=listingText($input,'q_grade');if($grade!==''){$where[]="EXISTS(SELECT 1 FROM job_grades g WHERE g.job_id=j.id AND g.grade COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '=')";$params[]=listingLike($grade);}
    return listingRows('SELECT j.* FROM jobs j WHERE '.implode(' AND ',$where).' ORDER BY j.title,j.id',$params,$input);
}
function searchDesignationHistory(array $actor,array $input): array {
    authorizeAdmin($actor);$where=['1=1'];$params=[];$role=listingText($input,'q_role');
    if($role!==''){ensure(in_array($role,['manager','subsecretary'],true),'Seleccione un rol de designación válido.');$where[]='a.role=?';$params[]=$role;}
    $name=listingText($input,'q_name');if($name!==''){$where[]="(u.name COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '=' OR v.name COLLATE utf8mb4_0900_ai_ci LIKE CONVERT(? USING utf8mb4) COLLATE utf8mb4_0900_ai_ci ESCAPE '=')";array_push($params,listingLike($name),listingLike($name));}
    $from=listingText($input,'q_from');$to=listingText($input,'q_to');
    ensure(($from===''||validDate($from))&&($to===''||validDate($to))&&($from===''||$to===''||$from<=$to),'Seleccione un intervalo de vigencia válido.');
    if($from!==''){$where[]='(a.end_date IS NULL OR a.end_date>=?)';$params[]=$from;}
    if($to!==''){$where[]='a.start_date<=?';$params[]=$to;}
    return listingRows('SELECT a.*,u.name principal,v.name alternate,d.name department,c.name creator FROM designations a JOIN users u ON u.id=a.user_id LEFT JOIN users v ON v.id=a.alternate_id LEFT JOIN departments d ON d.id=a.department_id JOIN users c ON c.id=a.created_by WHERE '.implode(' AND ',$where).' ORDER BY a.start_date DESC,a.id DESC',$params,$input);
}
function searchRegisteredActs(array $actor,array $input): array {
    $institutional=hasRole($actor,'admin')||hasRole($actor,'payroll');$where=['1=1'];$params=[];
    if(!$institutional){$where[]='a.user_id=?';$params[]=$actor['id'];}
    listingMatch($where,$params,'a.number',listingText($input,'q_number'));
    if($institutional)foreach(['q_name'=>'u.name','q_identification'=>'u.identification'] as $key=>$column)listingMatch($where,$params,$column,listingText($input,$key));
    $base=' FROM acts a JOIN users u ON u.id=a.user_id WHERE '.implode(' AND ',$where);
    // Cada página contiene hasta diez resoluciones; todos sus registros coincidentes permanecen juntos.
    $listing=listingRows('SELECT a.number,MAX(a.id) latest_id,COUNT(*) records'.$base.' GROUP BY a.number ORDER BY latest_id DESC,a.number',$params,$input);
    $numbers=array_column($listing['items'],'number');$groups=[];
    if($numbers){$records=rows('SELECT a.*,u.name'.$base.' AND a.number IN ('.implode(',',array_fill(0,count($numbers),'?')).') ORDER BY a.id DESC',[...$params,...$numbers]);foreach($records as $record)$groups['n:'.$record['number']][]=$record;}
    foreach($listing['items'] as &$group)$group['acts']=$groups['n:'.$group['number']]??[];unset($group);
    return $listing;
}
