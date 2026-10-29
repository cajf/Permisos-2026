<?php
declare(strict_types=1);

/** Conserva los datos actuales del funcionario y las copias históricas de cada solicitud. */
function jobCatalog(): array {
    return rows('SELECT j.id job_id,j.title,j.code,g.id grade_id,g.grade FROM jobs j JOIN job_grades g ON g.job_id=j.id ORDER BY j.title,g.grade');
}
function resolveJob(array $data): array {
    $entry=row('SELECT j.title,j.code,g.grade FROM jobs j JOIN job_grades g ON g.job_id=j.id WHERE j.title=? AND j.code=? AND g.grade=?',[trim($data['job_title']??''),trim($data['code']??''),trim($data['grade']??'')]);
    ensure($entry!==null,'Seleccione un cargo, código y grado registrados en el catálogo de Talento Humano.');
    return ['job_title'=>$entry['title'],'code'=>$entry['code'],'grade'=>$entry['grade']];
}
function saveJob(array $actor,array $data): void {
    authorizeAdmin($actor);
    atomic(function() use($actor,$data) {
        $id=(int)($data['job_id']??0);$title=trim($data['title']??'');$code=trim($data['code']??'');$grade=trim($data['grade']??'');
        ensure($title!=='' && mb_strlen($title)<=180,'Indique el nombre del cargo (hasta 180 caracteres).');
        ensure($code!=='' && mb_strlen($code)<=180,'Indique el código del cargo (hasta 180 caracteres).');
        $old=$id?row('SELECT * FROM jobs WHERE id=?',[$id]):null;
        ensure(!$id || $old!==null,'El cargo no existe.');
        ensure(!row('SELECT id FROM jobs WHERE title=? AND id!=?',[$title,$id]),'Ese cargo ya existe. Agregue sus grados al registro existente.');
        if ($old) {
            run('UPDATE jobs SET title=?,code=? WHERE id=?',[$title,$code,$id]);
            run('UPDATE users SET job_title=?,code=? WHERE job_title COLLATE utf8mb4_0900_ai_ci=? AND code=?',[$title,$code,$old['title'],$old['code']]);
        } else {
            ensure($grade!=='' && mb_strlen($grade)<=180,'Indique el primer grado del cargo (hasta 180 caracteres).');
            $id=insert('jobs',['title'=>$title,'code'=>$code]);
            insert('job_grades',['job_id'=>$id,'grade'=>$grade]);
        }
        event((int)$actor['id'],$old?'Cargo actualizado':'Cargo registrado','Cargo '.$id.' · '.$title.' · Código '.$code);
    });
}
function saveJobGrade(array $actor,array $data): void {
    authorizeAdmin($actor);
    atomic(function() use($actor,$data) {
        $id=(int)($data['job_id']??0);$grade=trim($data['grade']??'');
        ensure(row('SELECT id FROM jobs WHERE id=?',[$id])!==null,'Seleccione un cargo existente.');
        ensure($grade!=='' && mb_strlen($grade)<=180,'Indique el grado (hasta 180 caracteres).');
        ensure(!row('SELECT id FROM job_grades WHERE job_id=? AND grade=?',[$id,$grade]),'El grado ya está registrado para ese cargo.');
        insert('job_grades',['job_id'=>$id,'grade'=>$grade]);
        event((int)$actor['id'],'Grado registrado','Cargo '.$id.' · Grado '.$grade);
    });
}

/** Migración idempotente: no inventa códigos ni grados faltantes. Rechaza códigos contradictorios. */
function seedJobCatalog(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM app_locks WHERE id=1 FOR UPDATE')->fetchColumn();
        foreach ($pdo->query('SELECT DISTINCT job_title,code,grade FROM users ORDER BY job_title,code,grade')->fetchAll() as $user) {
            $title=trim($user['job_title']);$code=trim($user['code']);$grade=trim($user['grade']);
            if ($title==='') continue;
            $find=$pdo->prepare('SELECT * FROM jobs WHERE title=?');$find->execute([$title]);$job=$find->fetch();
            if (!$job) {
                $pdo->prepare('INSERT INTO jobs(title,code) VALUES(?,?)')->execute([$title,$code]);
                $job=['id'=>(int)$pdo->lastInsertId(),'code'=>$code];
            }
            if ($job['code']==='' && $code!=='') {
                $pdo->prepare('UPDATE jobs SET code=? WHERE id=?')->execute([$code,$job['id']]);
                $job['code']=$code;
            }
            if ($code!=='' && $job['code']!==$code) throw new RuntimeException('El cargo '.$title.' tiene códigos diferentes. Unifique esos datos antes de migrar.');
            $pdo->prepare('INSERT INTO job_grades(job_id,grade) VALUES(?,?) ON DUPLICATE KEY UPDATE grade=grade')->execute([$job['id'],$grade]);
        }
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
}
