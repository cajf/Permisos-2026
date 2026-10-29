<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';require __DIR__.'/../app/bootstrap.php';
installMysqlSchema(db());
insert('departments',['name'=>'Migración aislada']);
$base=['name'=>'Prueba de catálogo','email'=>'prueba@ejemplo.invalid','password'=>'hash','job_title'=>'Auxiliar Administrativo','department_id'=>1];
insert('users',$base+['identification'=>'CM-1','username'=>'cm1','code'=>'','grade'=>'01']);
insert('users',$base+['identification'=>'CM-2','username'=>'cm2','code'=>'407','grade'=>'02']);
seedJobCatalog(db());
ensure(row('SELECT code FROM jobs')['code']==='407','No tomó el único código conocido.');
ensure((int)row('SELECT COUNT(*) n FROM job_grades')['n']===2,'No conservó ambos grados.');
ensure(row('SELECT code FROM users WHERE username=?',['cm1'])['code']==='','Alteró datos del funcionario durante el traslado.');
seedJobCatalog(db());
ensure((int)row('SELECT COUNT(*) n FROM jobs')['n']===1,'Duplicó el cargo al repetir.');
insert('users',$base+['identification'=>'CM-3','username'=>'cm3','code'=>'999','grade'=>'03']);
try {seedJobCatalog(db());throw new LogicException('Aceptó dos códigos distintos.');}
catch(RuntimeException $e) {ensure(str_contains($e->getMessage(),'códigos diferentes'),'Falló por otro motivo.');}
ensure(row('SELECT code FROM jobs')['code']==='407' && (int)row('SELECT COUNT(*) n FROM job_grades')['n']===2,'La migración fallida dejó cambios parciales.');
echo "Migración aislada verificada: código único conocido, valores faltantes, grados, idempotencia y reversión de conflictos.\n";
