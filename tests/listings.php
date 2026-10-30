<?php
$admin=person(5);$employee=person(1);
for($i=1;$i<=12;$i++){
 $job=insert('jobs',['title'=>'Profesional de listado '.sprintf('%02d',$i),'code'=>sprintf('%03d',$i)]);
 insert('job_grades',['job_id'=>$job,'grade'=>$i===1?'09':'01']);
 insert('acts',['user_id'=>1,'number'=>'RES-'.sprintf('%03d',$i),'issued_on'=>'2026-01-01','expires_on'=>'2027-01-01','granted_minutes'=>510,'created_at'=>now()]);
 $date=sprintf('2025-01-%02d',$i);
 insert('designations',['role'=>'manager','department_id'=>1,'user_id'=>2,'alternate_id'=>8,'start_date'=>$date,'end_date'=>$date,'created_by'=>5,'created_at'=>now()]);
}
insert('acts',['user_id'=>8,'number'=>'RES-001','issued_on'=>'2026-01-01','expires_on'=>'2027-01-01','granted_minutes'=>60,'created_at'=>now()]);
for($i=1;$i<=14;$i++)insert('users',['name'=>'Listado '.$i,'identification'=>'LIST-'.$i,'username'=>'listado'.$i,'email'=>'listado'.$i.'@ejemplo.invalid','job_title'=>'Cargo de listado','department_id'=>1,'roles'=>'[]','password'=>'sin-acceso']);
foreach(['usuarios'=>fn($input)=>searchUserDirectory($admin,$input),'cargos'=>fn($input)=>searchJobDirectory($admin,$input),'designaciones'=>fn($input)=>searchDesignationHistory($admin,$input),'resoluciones'=>fn($input)=>searchRegisteredActs($admin,$input)] as $label=>$search){
 $first=$search([]);$second=$search(['list_page'=>2]);
 check(count($first['items'])===10&&$first['more']&&count($second['items'])>0,'Paginación de diez en '.$label);
 $key=$label==='resoluciones'?'number':'id';check(!array_intersect(array_column($first['items'],$key),array_column($second['items'],$key)),'Sin duplicados entre páginas de '.$label);
}
check(count(searchUserDirectory($admin,['q_name'=>'subsecretario'])['items'])===1,'Directorio filtra nombre sin distinguir mayúsculas');
check(count(searchUserDirectory($admin,['q_username'=>'qa1'])['items'])===1,'Directorio filtra usuario');
check(count(searchUserDirectory($admin,['q_identification'=>'PRUEBA-1'])['items'])===1,'Directorio filtra cédula');
check(count(searchUserDirectory($admin,['q_job'=>'Jefe'])['items'])===1,'Directorio filtra cargo seleccionado');
check(count(searchUserDirectory($admin,['q_role'=>'payroll'])['items'])===1,'Directorio filtra rol');
check(count(searchUserDirectory($admin,['q_role'=>'employee'])['items'])===10,'Directorio filtra funcionarios sin roles adicionales');
check(searchUserDirectory($admin,['q_name'=>"' OR 1=1 --"])['items']===[],'Nombre malicioso se trata como texto');
check(count(searchJobDirectory($admin,['q_job'=>'listado 01','q_code'=>'001','q_grade'=>'09'])['items'])===1,'Catálogo combina cargo, código y grado');
check(searchJobDirectory($admin,['q_grade'=>'99'])['items']===[],'Grado sin coincidencias no devuelve cargos');
$group=searchRegisteredActs($admin,['q_number'=>'RES-001']);
check(count($group['items'])===1&&count($group['items'][0]['acts'])===2,'Resolución agrupa registros de varios funcionarios');
$payroll=person(7);$payroll['roles']='["payroll"]';
check(count(searchRegisteredActs($payroll,['q_number'=>'RES-001'])['items'][0]['acts'])===2,'Nómina sin rol administrativo consulta actos institucionales');
$own=searchRegisteredActs($employee,['q_number'=>'RES-001','q_name'=>'Otro funcionario','q_identification'=>'PRUEBA-8']);
check(count($own['items'])===1&&count($own['items'][0]['acts'])===1&&(int)$own['items'][0]['acts'][0]['user_id']===1,'Funcionario solo ve actos propios y no puede ampliar alcance mediante filtros');
check(count(searchRegisteredActs($admin,['q_name'=>'otro funcionario'])['items'][0]['acts'])===1,'Nómina filtra actos por nombre del funcionario');
check(count(searchRegisteredActs($admin,['q_identification'=>'PRUEBA-8'])['items'][0]['acts'])===1,'Nómina filtra actos por cédula');
check(count(searchDesignationHistory($admin,['q_role'=>'manager','q_name'=>'jefe','q_from'=>'2025-01-04','q_to'=>'2025-01-06'])['items'])===3,'Historial combina rol, principal y vigencia');
check(count(searchDesignationHistory($admin,['q_name'=>'otro funcionario','q_from'=>'2025-01-01','q_to'=>'2025-01-01'])['items'])===1,'Historial busca también el suplente');
insert('designations',['role'=>'subsecretary','user_id'=>3,'start_date'=>'2025-02-01','created_by'=>5,'created_at'=>now()]);
check(count(searchDesignationHistory($admin,['q_role'=>'subsecretary','q_from'=>'2026-01-01','q_to'=>'2026-12-31'])['items'])===1,'Vigencia abierta coincide con el intervalo posterior');
refuses(fn()=>searchDesignationHistory($admin,['q_from'=>'2026-01-02','q_to'=>'2026-01-01']),'Rechaza intervalo invertido de designaciones');
refuses(fn()=>searchUserDirectory($admin,['q_role'=>'admin OR 1=1']),'Rechaza rol inválido');
foreach([fn()=>searchUserDirectory($employee,[]),fn()=>searchJobDirectory($employee,[]),fn()=>searchDesignationHistory($employee,[])] as $search)refuses($search,'Consultas administrativas protegidas del funcionario');
file_put_contents(ROOT.'/.work/listings-test-result.json',json_encode(['database_config'=>$GLOBALS['test_configuration_file'],'checks'=>$checks]));
echo $checks." comprobaciones de listados correctas.\n";
