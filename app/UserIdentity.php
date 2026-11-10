<?php
declare(strict_types=1);

function replaceUserRoles(int $id,array $roles): void {
    run('DELETE FROM user_roles WHERE user_id=?',[$id]);
    foreach (array_unique($roles) as $role) insert('user_roles',['user_id'=>$id,'role_code'=>$role]);
}

/** Writes the three parts of an account inside the caller's transaction. */
function saveUserIdentity(?int $id,array $person,array $employment,array $account,?array $roleCodes=null): int {
    if (!db()->inTransaction()) throw new LogicException('La identidad requiere una transacción.');
    if ($id===null) {
        $generatedId=insert('persons',$person);
        $id=(int)($person['id']??$generatedId);
        insert('users',['id'=>$id]+$account);
        insert('person_employments',['person_id'=>$id]+$employment);
    } else {
        foreach ([['persons','id',$person],['users','id',$account],['person_employments','person_id',$employment]] as [$table,$key,$fields]) {
            if (!$fields) continue;
            $assignments=implode(',',array_map(fn($column)=>mysqlIdentifier($column).'=?',array_keys($fields)));
            run('UPDATE '.mysqlIdentifier($table).' SET '.$assignments.' WHERE '.mysqlIdentifier($key).'=?',[...array_values($fields),$id]);
        }
    }
    if ($roleCodes!==null) replaceUserRoles($id,$roleCodes);
    return $id;
}

/** Catálogo mínimo para las cuentas ficticias de instalación. */
function createDemoIdentity(array $record): int {
    run('INSERT INTO jobs(title,code) VALUES(?,?) ON DUPLICATE KEY UPDATE id=id',[$record['job_title'],$record['code']??'']);
    $job=row('SELECT id FROM jobs WHERE title=?',[$record['job_title']]);
    $grade=$record['grade']??'';
    run('INSERT INTO job_grades(job_id,grade) VALUES(?,?) ON DUPLICATE KEY UPDATE id=id',[$job['id'],$grade]);
    return saveUserIdentity(null,
        array_intersect_key($record,array_flip(['id','name','identification','email','birth_date'])),
        ['job_id'=>$job['id'],'grade'=>$grade,'employment_type'=>'Carrera administrativa','department_id'=>$record['department_id'],'high_rank'=>$record['high_rank']??''],
        array_intersect_key($record,array_flip(['username','password','force_password'])),
        roles($record)
    );
}
