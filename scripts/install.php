<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/database/holidays.php';
db()->exec(file_get_contents(ROOT.'/database/schema.sql'));
if (row('SELECT id FROM users LIMIT 1')) { echo "La base de datos ya está inicializada. No se modificaron datos.\n"; exit; }
atomic(function() {
    foreach (['Secretaría Administrativa','Secretaría de Planeación','Despacho del Alcalde'] as $name) insert('departments',['name'=>$name]);
    $password='Local!'.bin2hex(random_bytes(6));
    $base=['identification'=>'','email'=>'','password'=>password_hash($password,PASSWORD_DEFAULT),'job_title'=>'','department_id'=>1,'force_password'=>1];
    $people=[
      ['name'=>'Administrador de demostración','username'=>'admin','roles'=>'["admin"]','job_title'=>'Administrador de Talento Humano'],
      ['name'=>'Subsecretario de demostración','username'=>'subsecretario','roles'=>'["subsecretary","manager"]','job_title'=>'Subsecretario administrativo del talento humano'],
      ['name'=>'Alcalde de demostración','username'=>'alcalde','roles'=>'["mayor","manager"]','job_title'=>'Alcalde municipal','department_id'=>3],
      ['name'=>'Jefe de demostración','username'=>'jefe','roles'=>'["manager"]','job_title'=>'Director de grupo'],
      ['name'=>'Funcionario de demostración','username'=>'funcionario','roles'=>'[]','job_title'=>'Profesional universitario','code'=>'219','grade'=>'24'],
      ['name'=>'Asesor de demostración','username'=>'asesor','roles'=>'[]','job_title'=>'Asesor de Despacho','high_rank'=>'Asesor de Despacho','department_id'=>3],
    ];
    foreach ($people as $i=>$p) insert('users',array_replace($base,$p,['identification'=>'DEMO-'.($i+1),'email'=>$p['username'].'@ejemplo.invalid']));
    run('UPDATE users SET manager_id=4 WHERE id IN (1,5,6)');
    run('UPDATE users SET manager_id=2 WHERE id=4');
    run('UPDATE users SET manager_id=3 WHERE id=2');
    setSetting('subsecretary_id','2'); setSetting('mayor_id','3');
    foreach (require ROOT.'/database/catalog.php' as $c) insert('categories',array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$c));
    for($y=(int)date('Y');$y<=(int)date('Y')+2;$y++)foreach(nationalHolidays($y)as $date=>$name)run('INSERT OR IGNORE INTO holidays(date,name)VALUES(?,?)',[$date,$name]);
    $text="ACCESO LOCAL DE DEMOSTRACIÓN\n\nURL: http://127.0.0.1:8088\nUsuarios: admin, funcionario, jefe, subsecretario, alcalde, asesor\nContraseña inicial: $password\n\nTodos los datos son ficticios. Cada cuenta debe cambiar la contraseña al ingresar.\nNo publique estos datos ni esta base de demostración en Internet.\n";
    file_put_contents(ROOT.'/storage/acceso-local.txt',$text);
});
echo "Base creada. Credenciales en storage/acceso-local.txt.\n";
