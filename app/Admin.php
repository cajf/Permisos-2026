<?php
declare(strict_types=1);

function saveUser(array $actor,array $data,?int $id=null): void {
    authorizeAdmin($actor);
    atomic(function() use($actor,$data,$id) {
        $existing=$id?row('SELECT * FROM users WHERE id=?',[$id]):null;
        if ($id) ensure($existing!==null,'Funcionario no encontrado.');
        foreach (['name','identification','username','email','job_title'] as $field) ensure(trim($data[$field]??'')!=='','Complete los campos obligatorios del funcionario.');
        ensure(filter_var($data['email'],FILTER_VALIDATE_EMAIL)!==false,'El correo no es válido.');
        ensure(preg_match('/^[a-zA-Z0-9._@-]{3,80}$/D',$data['username'])===1,'El usuario admite de 3 a 80 letras, números, puntos, @, guiones y guiones bajos.');
        ensure(row('SELECT id FROM departments WHERE id=?',[(int)$data['department_id']])!==null,'Seleccione una dependencia válida.');
        $newRoles=array_values(array_intersect($data['roles']??[],['admin','manager','subsecretary','mayor']));
        ensure(in_array($data['high_rank']??'',['','Asesor de Despacho','Secretario de Despacho','Jefe de Oficina'],true),'Clasificación de alto rango no válida.');
        if ($id===(int)$actor['id']) ensure(in_array('admin',$newRoles,true) && (int)($data['active']??0)===1,'No puede retirar su propio acceso de administrador.');
        $manager=(int)($data['manager_id']??0); $deputy=(int)($data['deputy_manager_id']??0);
        foreach ([$manager,$deputy] as $boss) if ($boss) { $u=row('SELECT * FROM users WHERE id=? AND active=1',[$boss]); ensure($u && hasRole($u,'manager'),'El jefe y su suplente deben tener rol de jefe inmediato y estar activos.'); }
        $values=[trim($data['name']),trim($data['identification']),trim($data['username']),trim($data['email']),trim($data['job_title']),trim($data['code']??''),trim($data['grade']??''),$data['appointment_type']??'Carrera administrativa',(int)$data['department_id'],$manager?:null,$deputy?:null,$data['high_rank']??'',json_encode($newRoles),(int)($data['active']??1)];
        if ($id) run('UPDATE users SET name=?,identification=?,username=?,email=?,job_title=?,code=?,grade=?,appointment_type=?,department_id=?,manager_id=?,deputy_manager_id=?,high_rank=?,roles=?,active=? WHERE id=?',[...$values,$id]);
        else {
            $password=$data['initial_password']??''; validatePassword($password);
            $keys=['name','identification','username','email','job_title','code','grade','appointment_type','department_id','manager_id','deputy_manager_id','high_rank','roles','active'];
            $id=insert('users',array_combine($keys,$values)+['password'=>password_hash($password,PASSWORD_DEFAULT)]);
        }
        (new Workflow())->refreshAssignments((int)$actor['id']);
        event((int)$actor['id'],'Funcionario actualizado','Funcionario '.$id);
    });
}
function validatePassword(string $password): void { ensure(strlen($password)>=12 && strlen($password)<=128 && preg_match('/[A-Za-z]/',$password) && preg_match('/[0-9]/',$password),'La contraseña debe tener de 12 a 128 caracteres, con letras y números.'); }
function readImport(string $path,string $extension): array {
    ensure(in_array($extension,['xlsx','csv'],true),'Importe un archivo XLSX o CSV.');
    if ($extension==='xlsx') {
        $zip=new ZipArchive(); ensure($zip->open($path)===true,'El XLSX no es válido.'); $expanded=0;
        for($i=0;$i<$zip->numFiles;$i++) $expanded+=(int)$zip->statIndex($i)['size']; $zip->close();
        ensure($expanded<=30*1024*1024,'El XLSX expandido supera 30 MB.');
        $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx(); $reader->setReadDataOnly(true);
        $book=$reader->load($path); $sheet=$book->getSheet(0);
        ensure($sheet->getHighestRow()<=1001 && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn())<=20,'Importe hasta 1.000 funcionarios y 20 columnas.');
        $values=$sheet->toArray(null,false,false,false); $book->disconnectWorksheets();
    } else {
        $h=fopen($path,'rb'); $first=fgets($h); rewind($h); $delimiter=substr_count($first,';')>substr_count($first,',')?';':','; $values=[];
        while(($line=fgetcsv($h,0,$delimiter,'"',''))!==false) { $values[]=$line; ensure(count($values)<=1001,'Importe hasta 1.000 funcionarios.'); } fclose($h);
    }
    $head=array_map(fn($s)=>strtolower(trim((string)$s," \t\n\r\0\x0B\xEF\xBB\xBF")),array_shift($values)??[]);
    $required=['cedula','nombre','usuario','correo','cargo','dependencia','cedula_jefe'];
    foreach ($required as $key) ensure(in_array($key,$head,true),'Falta la columna '.$key.'. Descargue la plantilla.');
    ensure(count($head)===count(array_unique($head)),'Hay encabezados repetidos.'); $data=[];
    foreach ($values as $line) {
        if (count(array_filter($line,fn($v)=>$v!==null && $v!==''))===0) continue;
        $line=array_slice(array_pad($line,count($head),''),0,count($head));
        foreach ($line as &$v) { $v=trim((string)$v); ensure(!preg_match('/^[=+@]/',$v),'La importación no admite fórmulas. Convierta esas celdas a valores.'); } unset($v);
        $data[]=array_combine($head,$line);
    }
    ensure(count($data)>0,'No se encontraron funcionarios.'); return $data;
}
function commitImport(array $actor,array $data): array {
    authorizeAdmin($actor);
    return atomic(function() use($actor,$data) {
        $passwords=[]; $ids=[]; $seen=[];
        foreach ($data as $i=>$r) {
            ensure($r['cedula']!=='' && $r['nombre']!=='' && $r['cargo']!=='' && $r['dependencia']!=='','Faltan datos en la fila '.($i+2).'.');
            ensure(!isset($seen[$r['cedula']]),'La cédula '.$r['cedula'].' está repetida.'); $seen[$r['cedula']]=true;
            ensure(filter_var($r['correo'],FILTER_VALIDATE_EMAIL)!==false,'Correo no válido en la fila '.($i+2).'.');
            ensure(preg_match('/^[a-zA-Z0-9._@-]{3,80}$/D',$r['usuario'])===1,'Usuario no válido en la fila '.($i+2).'.');
            $rank=$r['alto_rango']??''; ensure(in_array($rank,['','Asesor de Despacho','Secretario de Despacho','Jefe de Oficina'],true),'Alto rango no válido en la fila '.($i+2).'.');
            run('INSERT OR IGNORE INTO departments(name) VALUES(?)',[$r['dependencia']]); $dep=row('SELECT id FROM departments WHERE name=?',[$r['dependencia']]);
            $old=row('SELECT * FROM users WHERE identification=?',[$r['cedula']]);
            $fields=['name'=>$r['nombre'],'username'=>$r['usuario'],'email'=>$r['correo'],'job_title'=>$r['cargo'],'code'=>$r['codigo']??'','grade'=>$r['grado']??'','department_id'=>$dep['id'],'appointment_type'=>$r['vinculacion']??'Carrera administrativa','high_rank'=>$rank];
            if ($old) { run('UPDATE users SET name=?,username=?,email=?,job_title=?,code=?,grade=?,department_id=?,appointment_type=?,high_rank=? WHERE id=?',[...array_values($fields),$old['id']]); $ids[$r['cedula']]=$old['id']; }
            else { $pass='Inicio!'.bin2hex(random_bytes(6)); $ids[$r['cedula']]=insert('users',$fields+['identification'=>$r['cedula'],'password'=>password_hash($pass,PASSWORD_DEFAULT)]); $passwords[]=[$r['usuario'],$pass]; }
        }
        foreach ($data as $r) {
            $manager=$r['cedula_jefe']!==''?row('SELECT * FROM users WHERE identification=? AND active=1',[$r['cedula_jefe']]):null;
            ensure($r['cedula_jefe']===''||$manager!==null,'No se encontró el jefe con cédula '.$r['cedula_jefe'].'. Importe también ese funcionario.');
            if ($manager) { $roles=roles($manager); if (!in_array('manager',$roles,true)) { $roles[]='manager'; run('UPDATE users SET roles=? WHERE id=?',[json_encode($roles),$manager['id']]); } }
            run('UPDATE users SET manager_id=? WHERE id=?',[$manager['id']??null,$ids[$r['cedula']]]);
        }
        (new Workflow())->refreshAssignments((int)$actor['id']); event((int)$actor['id'],'Importación de funcionarios',count($data).' registros');
        return $passwords;
    });
}
function saveSchedule(array $actor,array $data): void {
    authorizeAdmin($actor); $a=$data['start_date']??''; $b=$data['end_date']??'';
    ensure(validDate($a)&&validDate($b)&&$a<=$b,'Revise la vigencia del horario.');
    ensure($a>=date('Y-m-d'),'Los horarios temporales solo pueden comenzar hoy o en el futuro.');
    $periods=[];
    for ($d=1;$d<=5;$d++) {
        $p=[]; for($i=0;$i<3;$i++) { $start=$data['periods'][$d][$i][0]??''; $end=$data['periods'][$d][$i][1]??''; if ($start===''&&$end==='') continue;
            ensure(preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$start)===1 && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$end)===1 && $end>$start,'Revise las franjas del horario temporal.');
            if ($p) ensure($start>=$p[count($p)-1][1],'Las franjas deben estar ordenadas y no superponerse.'); $p[]=[$start,$end];
        } ensure(count($p)>0,'Cada día de lunes a viernes debe tener al menos una franja.'); $periods[(string)$d]=$p;
    }
    atomic(function() use($actor,$a,$b,$periods,$data) {
        ensure(!row('SELECT id FROM schedules WHERE start_date<=? AND end_date>=?',[$b,$a]),'Ya existe un horario temporal que coincide con esas fechas.');
        insert('schedules',['name'=>trim($data['name']??'Horario temporal'),'start_date'=>$a,'end_date'=>$b,'periods'=>json_encode($periods),'created_by'=>$actor['id']]);
        event((int)$actor['id'],'Horario temporal registrado',"$a a $b. Las solicitudes existentes conservan sus minutos y franjas originales.");
    });
}
