<?php
declare(strict_types=1);
require __DIR__.'/mail-run.php';
atomic(fn()=>replaceUserRoles(7,['superadmin']));$super=person(7);
check(!can(person(1),'view.users'),'Funcionario no tiene acceso al directorio');
check(can(person(5),'users.edit')&&!can(person(5),'permissions.manage'),'Talento Humano conserva gestión sin administrar permisos');
foreach(array_keys(permissionCatalog()) as $key)check(can($super,$key),'Superadministrador: '.$key);
refuses(fn()=>saveRolePermissions(person(5),['role'=>'employee']),'Administrador ordinario no modifica permisos');
refuses(fn()=>saveRolePermissions($super,['role'=>'superadmin']),'No permite restringir al superadministrador');
refuses(fn()=>saveRolePermissions($super,['role'=>'employee','permissions'=>['inventado']]),'Rechaza permisos desconocidos');
saveRolePermissions($super,['role'=>'employee','version'=>'0','permissions'=>['view.dashboard','view.jobs']]);
check(can(person(1),'view.jobs')&&!can(person(1),'jobs.edit'),'Permite acceso de lectura independiente de edición');
refuses(fn()=>authorizeAction(person(1),'job'),'Servidor bloquea edición aunque permita ver');
refuses(fn()=>saveRolePermissions($super,['role'=>'employee','version'=>'0','permissions'=>[]]),'Detecta edición concurrente de la matriz');
refuses(fn()=>saveUser(person(5),[],7),'Otro administrador no puede editar al superadministrador');
$managerPermissions=array_diff(defaultRolePermissions('manager'),['request.approve']);
saveRolePermissions($super,['role'=>'manager','version'=>'1','permissions'=>array_values($managerPermissions)]);
refuses(fn()=>authorizeAction(person(2),'approve_bulk'),'Revocar aprobación también impide la aprobación masiva');
refuses(fn()=>(new Workflow())->review(person(2),1,'approved',''),'Servicio de revisión exige permiso sin depender del formulario');
check(can(person(3),'request.approve'),'La unión de roles conserva permisos de otro rol autorizado');
refuses(fn()=>authorizeRoute(person(1),'download'),'Descarga sin permiso denegada antes de leer soporte');
$html=permissionControls('<form method="post"><input name="action" value="job"><button>Guardar</button></form><a href="/?page=users">Directorio</a>',$person=person(1));
check(!str_contains($html,'Guardar')&&!str_contains($html,'Directorio'),'Oculta acciones y enlaces no autorizados');
check(row("SELECT id FROM events WHERE action='Permisos de rol actualizados'")!==null,'Audita los cambios de permisos');
insert('jobs',['title'=>'Cargo de prueba RBAC','code'=>'RBAC']);$jobId=(int)db()->lastInsertId();insert('job_grades',['job_id'=>$jobId,'grade'=>'1']);
$self=person(7);$self['job_title']='Cargo de prueba RBAC';$self['code']='RBAC';$self['grade']='1';$self['roles']=['admin'];
refuses(fn()=>saveUser($super,$self,7),'No se puede retirar al último superadministrador');
$staff=saveDirectoryRecord($super,array_replace($self,[
    'name'=>'Funcionario sin cuenta QA','identification'=>'STAFF-ONLY-QA','email'=>'staff-only@example.test',
]),null,true,false);
check(row('SELECT id FROM users WHERE id=?',[$staff])===null,'Registrar funcionario no crea una cuenta');
$entry=searchUserDirectory($super,['q_identification'=>'STAFF-ONLY-QA'])['items'][0]??null;
check($entry && (int)$entry['id']===$staff && !array_key_exists('username',$entry),'El directorio incluye al funcionario sin mostrar datos de cuenta');
check(!searchAccountDirectory($super,['q_identification'=>'STAFF-ONLY-QA'])['items'],'El directorio de usuarios excluye funcionarios sin cuenta');
saveDirectoryRecord($super,['username'=>'staff.only.qa','initial_password'=>'StaffTest2026','active'=>1,'roles'=>['payroll']],$staff,false,true);
$linked=person($staff);
check(count(searchAccountDirectory($super,['q_username'=>'staff.only.qa','q_role'=>'payroll'])['items'])===1,'El directorio de usuarios filtra por usuario y rol');
check($linked['name']==='Funcionario sin cuenta QA' && $linked['username']==='staff.only.qa' && roles($linked)===['payroll'],
    'Crear acceso reutiliza la persona y guarda los roles');
saveDirectoryRecord($super,['username'=>'staff.only.qa','active'=>0,'roles'=>['payroll']],$staff,false,true);
check((int)person($staff)['active']===0,'Desactivar acceso conserva el funcionario');
refuses(fn()=>saveDirectoryRecord($super,['username'=>$self['username'],'active'=>0,'roles'=>['superadmin']],7,false,true),
    'El formulario de cuentas protege al último superadministrador');
$own=testSubmit(7,'ordinario',[slot('2026-11-24','07:30','08:30')]);
try{$wf->review($super,$own,'approved','');check(false,'Autoaprobación debe fallar');}catch(DomainException $e){check(str_contains($e->getMessage(),'No le corresponde'),'Superadministrador tampoco puede autoaprobar');}
file_put_contents(ROOT.'/.work/permissions-test-result.json',json_encode(['database_config'=>$GLOBALS['test_configuration_file'],'checks'=>$checks]));
echo $checks." comprobaciones de permisos y correos correctas.\n";
