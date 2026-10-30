<?php
$day=date('Y-m-d');$input=['from'=>$day,'to'=>$day,'department'=>'all'];
$data=json_encode(['name'=>'María Pérez Gómez','types'=>[],'quotas'=>[],'acts'=>[]]);
foreach([1,2] as $id)insert('balance_snapshots',['user_id'=>$id,'department_id'=>1,'created_at'=>$day.' 08:00:00','data'=>$data]);
check(count(searchBalanceSnapshots(person(5),$input)['items'])===2,'Administrador consulta saldos del intervalo');
check(count(searchBalanceSnapshots(person(1),$input)['items'])===1,'Funcionario solo obtiene sus propios saldos');
check(!canFilterBalanceDepartment(person(1))&&canFilterBalanceDepartment(person(5)),'Funcionario sin filtro de dependencia; administrador conserva selector');
check(count(searchBalanceSnapshots(person(1),array_replace($input,['department'=>'999']))['items'])===1,'Funcionario consulta todas sus dependencias aunque llegue filtro ajeno por URL');
check(count(searchBalanceSnapshots(person(1),array_replace($input,['department'=>"1 OR 1=1"]))['items'])===1,'Servidor ignora dependencia manipulada para consulta personal');
check(count(searchBalanceSnapshots(person(1),$input+['name'=>'Otra persona'])['items'])===1,'Servidor ignora nombre ajeno en consulta personal');
check(count(searchBalanceSnapshots(person(1),$input+['name'=>str_repeat('x',121)])['items'])===1,'Nombre enviado por URL no impide consultar los propios saldos');
check(count(searchBalanceSnapshots(person(5),$input+['name'=>'perez'])['items'])===2,'Busca apellidos sin exigir mayúsculas o tildes');
check(searchBalanceSnapshots(person(5),$input+['name'=>"' OR 1=1 --"])['items']===[],'Nombre se trata como parámetro literal');
check(searchBalanceSnapshots(person(5),array_replace($input,['department'=>'999']))['items']===[],'Dependencia filtra antes de cargar datos');
refuses(fn()=>searchBalanceSnapshots(person(5),array_replace($input,['from'=>'2099-12-31'])),'Rechaza fechas invertidas');
refuses(fn()=>searchBalanceSnapshots(person(5),array_replace($input,['department'=>'1 OR 1=1'])),'Rechaza dependencia malformada');
refuses(fn()=>searchBalanceSnapshots(person(5),['from'=>'2025-12-31','to'=>'2026-01-01']),'Rechaza intervalo que cruza de año');
check(searchBalanceSnapshots(person(5),['from'=>'2025-01-01','to'=>'2025-12-31'])['items']===[],'Permite consultar un año anterior completo');
insert('designations',['role'=>'manager','department_id'=>1,'user_id'=>2,'start_date'=>$day,'created_by'=>5,'created_at'=>now()]);
check(count(searchBalanceSnapshots(person(2),$input)['items'])===2,'Jefe consulta su dependencia durante su designación');
insert('balance_snapshots',['user_id'=>1,'department_id'=>1,'created_at'=>$day.' 09:00:00','data'=>$data]);
check(count(searchBalanceSnapshots(person(5),$input)['items'])===2,'Múltiples movimientos no duplican al funcionario');
check(searchBalanceSnapshots(person(5),$input)['items'][0]['created_at']===$day.' 09:00:00','Selecciona el último saldo del intervalo');
insert('departments',['name'=>'Dependencia de traslado']);run('UPDATE users SET department_id=2 WHERE id=1');
insert('balance_snapshots',['user_id'=>1,'department_id'=>2,'created_at'=>$day.' 11:00:00','data'=>$data]);
$personal=searchBalanceSnapshots(person(1),array_replace($input,['department'=>'1']));
check(count($personal['items'])===1&&(int)$personal['items'][0]['department_id']===2,'Traslado no oculta el saldo personal aunque el enlace conserve dependencia anterior');
for($i=0;$i<22;$i++){
 $id=insert('users',['name'=>'Prueba paginación '.$i,'username'=>'balance'.$i,'identification'=>'BAL-'.$i,'email'=>'b'.$i.'@ejemplo.invalid','job_title'=>'Prueba','password'=>'sin-acceso','department_id'=>1,'roles'=>'[]']);
 insert('balance_snapshots',['user_id'=>$id,'department_id'=>1,'created_at'=>$day.' 10:00:00','data'=>$data]);
}
$first=searchBalanceSnapshots(person(5),$input);$second=searchBalanceSnapshots(person(5),$input+['results_page'=>2]);
check(count($first['items'])===20&&$first['more']&&count($second['items'])===4&&!$second['more'],'Paginación limita carga a 20 funcionarios y detecta siguiente página');
check(!array_intersect(array_column($first['items'],'id'),array_column($second['items'],'id')),'Páginas sin duplicados');
echo $checks." comprobaciones de consulta de saldos correctas.\n";
