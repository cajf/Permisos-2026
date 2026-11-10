<?php
declare(strict_types=1);

function saveUser(array $actor,array $data,?int $id=null): void {
    requirePermission($actor,'users.edit');
    atomic(function() use($actor,$data,$id) {
        $existing=$id?row('SELECT * FROM user_profiles WHERE id=?',[$id]):null;
        if ($id) ensure($existing!==null,'Funcionario no encontrado.');
        ensure(!$existing || !isSuperadmin($existing) || isSuperadmin($actor),'Solo un superadministrador puede modificar la cuenta de otro superadministrador.');
        $data=array_replace($data,resolveJob($data));
        if (!$existing && !isOfficialWorker($data)) ensure($data['code']!=='' && $data['grade']!=='','Complete el código y los grados del cargo antes de asignarlo a un nuevo funcionario.');
        foreach (['name','identification','username','email','job_title'] as $field) ensure(trim($data[$field]??'')!=='','Complete los campos obligatorios del funcionario.');
        ensure(filter_var($data['email'],FILTER_VALIDATE_EMAIL)!==false,'El correo no es válido.');
        ensure(preg_match('/^[a-zA-Z0-9._@-]{3,80}$/D',$data['username'])===1,'El usuario admite de 3 a 80 letras, números, puntos, @, guiones y guiones bajos.');
        ensure(row('SELECT id FROM departments WHERE id=?',[(int)$data['department_id']])!==null,'Seleccione una dependencia válida.');
        ensure(is_array($data['roles']??[]),'Los roles no son válidos.');
        $newRoles=array_values(array_intersect($data['roles']??[],['admin','manager','subsecretary','mayor','payroll','superadmin']));
        ensure(!in_array('superadmin',$newRoles,true)||isSuperadmin($actor),'Solo un superadministrador puede asignar ese rol.');
        if(!can($actor,'users.roles'))$newRoles=$existing?roles($existing):[];
        if($existing && isSuperadmin($existing) && (!in_array('superadmin',$newRoles,true)||!(int)($data['active']??1)))ensure((int)row("SELECT COUNT(*) n FROM user_roles r JOIN users u ON u.id=r.user_id WHERE r.role_code='superadmin' AND u.active=1 AND u.id<>?",[$id])['n']>0,'Debe conservar al menos un superadministrador activo.');
        if ($existing) {
            $pendingRoles=array_column(rows("SELECT DISTINCT a.role FROM approvals a JOIN requests r ON r.id=a.request_id WHERE a.assigned_user_id=? AND a.status='waiting' AND r.status='pending' AND a.revision=r.revision",[$id]),'role');
            if (row("SELECT id FROM requests WHERE cancellation_assignee_id=? AND status='cancel_pending' LIMIT 1",[$id])) $pendingRoles[]='subsecretary';
            foreach (rows('SELECT DISTINCT role FROM designations WHERE (user_id=? OR alternate_id=?) AND (end_date IS NULL OR end_date>=?)',[$id,$id,date('Y-m-d')]) as $assignment) $pendingRoles[]=$assignment['role'];
            foreach ($pendingRoles as $requiredRole) ensure(in_array($requiredRole,$newRoles,true) && (int)($data['active']??1)===1,'Conserve activo al responsable y su rol mientras tenga designaciones vigentes, futuras o trámites pendientes.');
        }
        $birth=trim($data['birth_date']??($existing['birth_date']??''));
        ensure($birth==='' || (validDate($birth) && $birth<date('Y-m-d')),'La fecha de nacimiento debe ser válida y anterior a hoy.');
        ensure(in_array($data['high_rank']??'',['','Asesor de Despacho','Secretario de Despacho','Jefe de Oficina'],true),'Clasificación de alto rango no válida.');
        if ($id===(int)$actor['id'] && hasRole($actor,'admin')) ensure((in_array('admin',$newRoles,true)||in_array('superadmin',$newRoles,true)) && (int)($data['active']??0)===1,'No puede retirar su propio acceso de administrador.');
        $person=['name'=>trim($data['name']),'identification'=>trim($data['identification']),'email'=>trim($data['email']),'birth_date'=>$birth?:null];
        $employment=['job_id'=>$data['job_id'],'grade'=>isOfficialWorker($data)?null:$data['grade'],'employment_type'=>$data['appointment_type']??'Carrera administrativa','department_id'=>(int)$data['department_id'],'high_rank'=>$data['high_rank']??''];
        $account=['username'=>trim($data['username']),'active'=>(int)($data['active']??1)];
        if (!$id) {
            $password=$data['initial_password']??''; validatePassword($password);
            $account['password']=password_hash($password,PASSWORD_DEFAULT);
        }
        $id=saveUserIdentity($id,$person,$employment,$account,$newRoles);
        captureBalances((int)$id);
        (new Workflow())->refreshAssignments((int)$actor['id']);
        event((int)$actor['id'],'Funcionario actualizado','Funcionario '.$id);
    });
}
function validatePassword(string $password): void { ensure(mb_strlen($password)>=8 && mb_strlen($password)<=15 && preg_match('/[A-Za-z]/',$password) && preg_match('/[0-9]/',$password),'La contraseña debe tener de 8 a 15 caracteres, con letras y números.'); }
function temporaryPassword(): string { return 'Th1'.bin2hex(random_bytes(6)); }
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
    requirePermission($actor,'import.execute');
    return atomic(function() use($actor,$data) {
        $passwords=[]; $ids=[]; $seen=[];
        foreach ($data as $i=>$r) {
            try { $job=resolveJob(['job_title'=>$r['cargo']??'','code'=>$r['codigo']??'','grade'=>$r['grado']??'','appointment_type'=>$r['vinculacion']??'Carrera administrativa']); }
            catch (DomainException $e) { throw new DomainException('Fila '.($i+2).': '.$e->getMessage().' Registre primero la combinación en Cargos, códigos y grados.'); }
            $r=array_replace($r,['cargo'=>$job['job_title'],'codigo'=>$job['code'],'grado'=>$job['grade']]);
            ensure($r['cedula']!=='' && $r['nombre']!=='' && $r['cargo']!=='' && $r['dependencia']!=='','Faltan datos en la fila '.($i+2).'.');
            ensure(!isset($seen[$r['cedula']]),'La cédula '.$r['cedula'].' está repetida.'); $seen[$r['cedula']]=true;
            ensure(filter_var($r['correo'],FILTER_VALIDATE_EMAIL)!==false,'Correo no válido en la fila '.($i+2).'.');
            ensure(preg_match('/^[a-zA-Z0-9._@-]{3,80}$/D',$r['usuario'])===1,'Usuario no válido en la fila '.($i+2).'.');
            $rank=$r['alto_rango']??''; ensure(in_array($rank,['','Asesor de Despacho','Secretario de Despacho','Jefe de Oficina'],true),'Alto rango no válido en la fila '.($i+2).'.');
            run('INSERT INTO departments(name) VALUES(?) ON DUPLICATE KEY UPDATE name=name',[$r['dependencia']]); $dep=row('SELECT id FROM departments WHERE name=?',[$r['dependencia']]);
            $old=row('SELECT * FROM user_profiles WHERE identification=?',[$r['cedula']]);
            ensure(!$old || !isSuperadmin($old) || isSuperadmin($actor),'La importación no puede modificar una cuenta de superadministrador.');
            $person=['name'=>$r['nombre'],'identification'=>$r['cedula'],'email'=>$r['correo']];
            $type=$r['vinculacion']??'Carrera administrativa';
            $employment=['job_id'=>$job['job_id'],'grade'=>$type==='Trabajador oficial'?null:$job['grade'],'employment_type'=>$type,'department_id'=>$dep['id'],'high_rank'=>$rank];
            $account=['username'=>$r['usuario']];
            if (!$old) {
                $pass=temporaryPassword();
                $account['password']=password_hash($pass,PASSWORD_DEFAULT);
                $passwords[]=[$r['usuario'],$pass];
            }
            if (array_key_exists('fecha_nacimiento',$r) && $r['fecha_nacimiento']!=='') {
                ensure(validDate($r['fecha_nacimiento']) && $r['fecha_nacimiento']<date('Y-m-d'),'Fecha de nacimiento no válida en la fila '.($i+2).'. Use AAAA-MM-DD.');
                $person['birth_date']=$r['fecha_nacimiento'];
            }
            $ids[$r['cedula']]=saveUserIdentity($old?(int)$old['id']:null,$person,$employment,$account);
        }
        foreach ($data as $r) {
            $manager=$r['cedula_jefe']!==''?row('SELECT * FROM user_profiles WHERE identification=? AND active=1',[$r['cedula_jefe']]):null;
            ensure($r['cedula_jefe']===''||$manager!==null,'No se encontró el jefe con cédula '.$r['cedula_jefe'].'. Importe también ese funcionario.');
            if ($manager) run("INSERT INTO user_roles(user_id,role_code) VALUES(?,'manager') ON DUPLICATE KEY UPDATE role_code=user_roles.role_code",[$manager['id']]);
            run('UPDATE person_employments SET manager_id=? WHERE person_id=?',[$manager['id']??null,$ids[$r['cedula']]]);
        }
        foreach ($ids as $userId) captureBalances((int)$userId);
        (new Workflow())->refreshAssignments((int)$actor['id']); event((int)$actor['id'],'Importación de funcionarios',count($data).' registros');
        return $passwords;
    });
}
function saveSchoolBreak(array $actor,array $data,?DateTimeImmutable $clock=null): void {
    requirePermission($actor,'calendar.edit');
    $year=($clock??new DateTimeImmutable())->format('Y');
    $start=$data['start_date']??'';
    $end=$data['end_date']??'';
    ensure(is_string($start) && is_string($end) && validDate($start) && validDate($end) && $start<=$end
        && substr($start,0,4)===$year && substr($end,0,4)===$year,'Indique un inicio y un fin ordenados dentro de la vigencia '.$year.'.');
    ensure((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days<7,'El receso escolar admite hasta siete días calendario consecutivos.');
    atomic(function() use($actor,$year,$start,$end) {
        setSetting('school_break_'.$year,json_encode(['start'=>$start,'end'=>$end],JSON_THROW_ON_ERROR));
        event((int)$actor['id'],'Semana de receso escolar actualizada',"$year · $start a $end");
    });
}

function saveSchedule(array $actor,array $data): void {
    requirePermission($actor,'calendar.edit'); $a=$data['start_date']??''; $b=$data['end_date']??'';
    $group=(string)($data['schedule_group']??'regular');ensure(in_array($group,['regular','official_worker'],true),'Seleccione una jornada válida.');
    ensure(validDate($a)&&validDate($b)&&$a<=$b,'Revise la vigencia del horario.');
    ensure($a>=date('Y-m-d'),'Los horarios temporales solo pueden comenzar hoy o en el futuro.');
    $periods=[];
    for ($d=1;$d<=5;$d++) {
        $p=[]; for($i=0;$i<3;$i++) { $start=$data['periods'][$d][$i][0]??''; $end=$data['periods'][$d][$i][1]??''; if ($start===''&&$end==='') continue;
            ensure(preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$start)===1 && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$end)===1 && $end>$start,'Revise las franjas del horario temporal.');
            if ($p) ensure($start>=$p[count($p)-1][1],'Las franjas deben estar ordenadas y no superponerse.'); $p[]=[$start,$end];
        } ensure(count($p)>0,'Cada día de lunes a viernes debe tener al menos una franja.'); $periods[(string)$d]=$p;
    }
    atomic(function() use($actor,$a,$b,$periods,$data,$group) {
        if($group==='official_worker'){
            $schedules=json_decode(setting('official_worker_schedules','[]'),true,512,JSON_THROW_ON_ERROR);
            foreach($schedules as $schedule)ensure($schedule['start_date']>$b||$schedule['end_date']<$a,'Ya existe un horario temporal de trabajador oficial que coincide con esas fechas.');
            $schedules[]=['name'=>trim($data['name']??'Horario temporal'),'start_date'=>$a,'end_date'=>$b,'periods'=>$periods,'created_by'=>(int)$actor['id'],'created_at'=>now()];
            setSetting('official_worker_schedules',json_encode($schedules,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            event((int)$actor['id'],'Horario temporal de trabajador oficial registrado',"$a a $b. Las solicitudes existentes conservan sus franjas y minutos.");return;
        }
        ensure(!row('SELECT id FROM schedules WHERE start_date<=? AND end_date>=?',[$b,$a]),'Ya existe un horario temporal que coincide con esas fechas.');
        insert('schedules',['name'=>trim($data['name']??'Horario temporal'),'start_date'=>$a,'end_date'=>$b,'periods'=>json_encode($periods),'created_by'=>$actor['id']]);
        event((int)$actor['id'],'Horario temporal registrado',"$a a $b. Las solicitudes existentes conservan sus minutos y franjas originales.");
    });
}
