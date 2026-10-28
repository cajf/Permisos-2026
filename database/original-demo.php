<?php
declare(strict_types=1);

/** Restaura únicamente los datos ficticios de la primera versión, con las reglas actuales. */
function restoreOriginalDemo(): array {
    require_once ROOT.'/database/holidays.php';
    return atomic(function (): array {
        if (tableExists('attachment_contents')) run('DELETE FROM attachment_contents');
        foreach (['balance_snapshots','outbox','events','attachments','allocations','approvals','request_slots','requests','acts','schedules','designations','holidays','categories','settings'] as $table) {
            run('DELETE FROM '.mysqlIdentifier($table));
        }
        run('UPDATE users SET manager_id=NULL,deputy_manager_id=NULL');
        run('DELETE FROM users');
        run('DELETE FROM departments');
        foreach (['Secretaría Administrativa','Secretaría de Planeación','Despacho del Alcalde'] as $i=>$name) insert('departments',['id'=>$i+1,'name'=>$name]);
        $people=[
            ['name'=>'Administrador de demostración','username'=>'admin','roles'=>'["admin"]','job_title'=>'Administrador de Talento Humano'],
            ['name'=>'Subsecretario de demostración','username'=>'subsecretario','roles'=>'["subsecretary","manager"]','job_title'=>'Subsecretario administrativo del talento humano'],
            ['name'=>'Alcalde de demostración','username'=>'alcalde','roles'=>'["mayor","manager"]','job_title'=>'Alcalde municipal','department_id'=>3],
            ['name'=>'Jefe de demostración','username'=>'jefe','roles'=>'["manager"]','job_title'=>'Director de grupo'],
            ['name'=>'Funcionario de demostración','username'=>'funcionario','roles'=>'[]','job_title'=>'Profesional universitario','code'=>'219','grade'=>'24'],
            ['name'=>'Asesor de demostración','username'=>'asesor','roles'=>'[]','job_title'=>'Asesor de Despacho','high_rank'=>'Asesor de Despacho','department_id'=>3],
        ];
        $credentials=[];
        foreach ($people as $i=>$person) {
            $password='Demo!'.bin2hex(random_bytes(10));
            $credentials[$person['username']]=$password;
            insert('users',array_replace(['id'=>$i+1,'identification'=>'DEMO-'.($i+1),'email'=>$person['username'].'@ejemplo.invalid','department_id'=>1,'force_password'=>1,'password'=>password_hash($password,PASSWORD_DEFAULT)],$person));
        }
        run('UPDATE users SET manager_id=4 WHERE id IN (1,5,6)');
        run('UPDATE users SET manager_id=2 WHERE id=4');
        run('UPDATE users SET manager_id=3 WHERE id=2');
        setSetting('subsecretary_id','2');setSetting('mayor_id','3');
        // Invalida también las sesiones abiertas antes de restaurar los mismos identificadores.
        setSetting('session_generation',bin2hex(random_bytes(24)));
        foreach (require ROOT.'/database/catalog.php' as $i=>$category) insert('categories',['id'=>$i+1]+array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$category));
        for ($year=(int)date('Y');$year<=(int)date('Y')+2;$year++) foreach (nationalHolidays($year) as $date=>$name) insert('holidays',['date'=>$date,'name'=>$name]);
        verifyMysqlReferences(db());
        return $credentials;
    });
}
