<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Admin.php';
require ROOT.'/app/Transfer.php';
require ROOT.'/app/Backup.php';

installMysqlSchema(db(),true);
$department=insert('departments',['name'=>'Dependencia de prueba']);
$job=insert('jobs',['title'=>'Auxiliar de prueba','code'=>'017']);
insert('job_grades',['job_id'=>$job,'grade'=>'01']);
$officialCategory=insert('official_job_categories',['name'=>'Categoría oficial de prueba']);
insert('jobs',['title'=>'Operario de prueba','code'=>'','employment_group'=>'official_worker','official_category_id'=>$officialCategory]);
$hash=password_hash('Solo!ParaPruebas12',PASSWORD_DEFAULT);
$base=['password'=>$hash,'department_id'=>$department,'email'=>'persona@ejemplo.invalid'];
$employee=insert('users',$base+['id'=>11,'name'=>'Persona de prueba','identification'=>'00123','username'=>'empleado','job_title'=>'Auxiliar de prueba','code'=>'017','grade'=>'01','birth_date'=>'1988-05-14','roles'=>'["admin","payroll"]']);
$official=insert('users',$base+['id'=>19,'name'=>'Oficial de prueba','identification'=>'00456','username'=>'oficial','job_title'=>'Operario de prueba','job_category'=>'Categoría oficial de prueba','appointment_type'=>'Trabajador oficial','manager_id'=>11,'roles'=>'["rol_inexistente"]']);
$category=insert('categories',['family'=>'permiso','name'=>'Ordinario','slug'=>'ordinario','supports'=>'']);
$request=insert('requests',['user_id'=>11,'category_id'=>$category,'total_minutes'=>60,'justification'=>'Conservar este registro histórico','snapshot'=>'{"job_title":"Cargo anterior"}','created_at'=>now(),'updated_at'=>now(),'department_id'=>$department]);

try {
    migrateDatabase(db());
    throw new RuntimeException('Aceptó un rol ajeno al catálogo.');
} catch (PDOException $error) {
    ensure(legacyUserSchema(db()),'Se retiró el esquema anterior a pesar del conflicto.');
    ensure((int)row('SELECT COUNT(*) n FROM persons')['n']===0,'La copia dejó filas parciales.');
}
run("UPDATE users SET roles='[]' WHERE id=19");
migrateDatabase(db());
migrateDatabase(db());
ensure(!legacyUserSchema(db()),'La tabla de acceso conserva atributos de identidad anteriores.');
$credentials=row('SELECT * FROM users WHERE id=11');
ensure(password_verify('Solo!ParaPruebas12',$credentials['password']),'El hash de acceso cambió.');
$profile=row('SELECT * FROM user_profiles WHERE id=11');
ensure($profile['identification']==='00123' && $profile['birth_date']==='1988-05-14' && $profile['code']==='017' && $profile['grade']==='01','Cambió la identidad o el cargo.');
ensure(hasRole($profile,'admin') && hasRole($profile,'payroll'),'Se perdieron roles.');
$worker=row('SELECT * FROM user_profiles WHERE id=19');
ensure(isOfficialWorker($worker) && (int)$worker['manager_id']===11 && $worker['job_category']==='Categoría oficial de prueba','Cambió la clasificación del trabajador oficial.');
$historical=row('SELECT user_id,snapshot FROM requests WHERE id=?',[$request]);
ensure((int)$historical['user_id']===11 && $historical['snapshot']==='{"job_title":"Cargo anterior"}','Cambió una solicitud histórica.');
try {
    run("UPDATE person_employments SET grade='99' WHERE person_id=11");
    throw new RuntimeException('Aceptó un grado fuera del cargo.');
} catch (PDOException) {}

saveJob($profile,['job_id'=>$job,'title'=>'Auxiliar renombrado','code'=>'018','employment_group'=>'regular']);
ensure(row('SELECT job_title,code FROM user_profiles WHERE id=11')===['job_title'=>'Auxiliar renombrado','code'=>'018'],'No reflejó el cambio del catálogo en el perfil.');
$data=['cedula'=>'00999','nombre'=>'Nueva persona','usuario'=>'nuevo','correo'=>'nuevo@ejemplo.invalid','cargo'=>'Auxiliar renombrado','codigo'=>'018','grado'=>'01','vinculacion'=>'Provisional','dependencia'=>'Dependencia de prueba','cedula_jefe'=>'00123'];
$access=commitImport($profile,[$data]);
ensure(count($access)===1 && $access[0][0]==='nuevo','No generó credenciales para una persona nueva.');
$newProfile=row('SELECT * FROM user_profiles WHERE username=?',['nuevo']);
ensure((int)$newProfile['manager_id']===11 && $newProfile['appointment_type']==='Provisional','No persistió la vinculación importada.');
$data['nombre']='Persona actualizada';
ensure(commitImport($profile,[$data])===[] && row('SELECT name FROM persons WHERE id=?',[$newProfile['id']])['name']==='Persona actualizada','La actualización duplicó la cuenta.');
verifyMysqlReferences(db());
$backupDirectory=ROOT.'/.work/identity-backup-'.bin2hex(random_bytes(5));
createMysqlBackup(db(),uploadDir(),$backupDirectory);
$restoreName='permisos_test_identity_'.bin2hex(random_bytes(5));
$admin->exec('CREATE DATABASE '.mysqlIdentifier($restoreName).' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin');
try {
    $restored=mysqlConnection(array_replace($adminConfig,['database'=>$restoreName]),true,true);
    restoreMysqlBackup($restored,$backupDirectory,$backupDirectory.'/restored-uploads');
    ensure((int)$restored->query('SELECT COUNT(*) FROM user_profiles')->fetchColumn()===3,'La restauración no reconstruyó la vista de perfiles.');
    ensure((int)$restored->query("SELECT COUNT(*) FROM user_roles WHERE user_id=11 AND role_code IN ('manager','admin','payroll')")->fetchColumn()===3,'La restauración no conservó los roles.');
} finally {
    $admin->exec('DROP DATABASE '.mysqlIdentifier($restoreName));
}
echo "Normalización verificada: migración, reintento, credenciales, roles, historia, catálogo, importación y restauración.\n";
