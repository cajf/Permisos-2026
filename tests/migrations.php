<?php
declare(strict_types=1);
$oldDatabase=new PDO('sqlite::memory:');$oldDatabase->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$oldDatabase->exec(file_get_contents(__DIR__.'/fixtures/schema-before-designations.sql'));
$oldDatabase->exec("INSERT INTO departments(id,name) VALUES(1,'Anterior');
INSERT INTO users(id,name,identification,username,email,password,job_title,department_id,manager_id,roles) VALUES(2,'Jefe anterior','ANT-2','anterior2','jefe@ejemplo.invalid','hash','Jefe',1,NULL,'[\"manager\"]');
INSERT INTO users(id,name,identification,username,email,password,job_title,department_id,manager_id,roles) VALUES(3,'Subsecretario anterior','ANT-3','anterior3','sub@ejemplo.invalid','hash','Subsecretario',1,2,'[\"subsecretary\"]');
INSERT INTO users(id,name,identification,username,email,password,job_title,department_id,manager_id) VALUES(1,'Titular anterior','ANT-1','anterior1','titular@ejemplo.invalid','hash','Funcionario',1,2);
INSERT INTO categories(id,family,name,slug,supports,supports_required) VALUES(1,'permiso','Académico','academico','Soporte',0);
INSERT INTO requests(id,user_id,category_id,justification,total_minutes,snapshot,created_at,updated_at) VALUES(1,1,1,'Solicitud previa',60,'{}','2026-10-26 08:00:00','2026-10-26 08:00:00');
INSERT INTO request_slots(request_id,date,start_time,end_time,minutes,segments,semester) VALUES(1,'2026-11-20','07:00','08:00',60,'[[\"07:00\",\"08:00\"]]','2026-2');
INSERT INTO approvals(request_id,revision,step,role,assigned_user_id) VALUES(1,1,1,'manager',2);
INSERT INTO approvals(request_id,revision,step,role,assigned_user_id) VALUES(1,1,2,'subsecretary',3);");
migrateDatabase($oldDatabase);migrateDatabase($oldDatabase);
check((int)$oldDatabase->query('SELECT assigned_user_id FROM approvals WHERE step=1')->fetchColumn()===2,'La migración real conserva al jefe de la etapa pendiente anterior');
check($oldDatabase->query('SELECT start_at FROM request_slots')->fetchColumn()==='2026-11-20 07:00:00','La migración incorpora fechas y horas completas sin alterar los minutos existentes');
check((int)$oldDatabase->query('SELECT supports_required FROM categories')->fetchColumn()===0,'La migración completa conserva los soportes opcionales configurados');
check((int)$oldDatabase->query('SELECT COUNT(*) FROM designations')->fetchColumn()===0,'La migración no fabrica designaciones históricas');
check($oldDatabase->query('PRAGMA foreign_key_check')->fetchAll()===[],'La actualización del esquema anterior conserva su integridad referencial');
