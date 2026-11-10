<?php
declare(strict_types=1);

/** Conserva los datos actuales del funcionario y las copias históricas de cada solicitud. */
function jobCatalog(): array {
    return rows("SELECT j.id job_id,j.title,j.code,j.employment_group,j.official_category_id,c.name job_category,g.id grade_id,COALESCE(g.grade,'') grade FROM jobs j LEFT JOIN job_grades g ON g.job_id=j.id AND j.employment_group='regular' LEFT JOIN official_job_categories c ON c.id=j.official_category_id ORDER BY j.title,g.grade");
}
function resolveJob(array $data): array {
    ensure(in_array($data['appointment_type']??'Carrera administrativa',['Carrera administrativa','Libre nombramiento y remoción','Provisional','Trabajador oficial'],true),'Seleccione un tipo de vinculación válido.');
    $job=row('SELECT j.*,c.name job_category FROM jobs j LEFT JOIN official_job_categories c ON c.id=j.official_category_id WHERE j.title=?',[trim($data['job_title']??'')]);
    ensure($job!==null,'Seleccione un cargo registrado.');
    $official=isOfficialWorker($data);
    ensure(($job['employment_group']==='official_worker')===$official,'El cargo no corresponde al tipo de vinculación seleccionado.');
    if($official){ensure(!empty($job['job_category']),'Asigne una categoría al cargo de trabajador oficial.');return ['job_id'=>$job['id'],'job_title'=>$job['title'],'code'=>'','grade'=>'','job_category'=>$job['job_category']];}
    $entry=row('SELECT j.title,j.code,g.grade FROM jobs j JOIN job_grades g ON g.job_id=j.id WHERE j.title=? AND j.code=? AND g.grade=?',[trim($data['job_title']??''),trim($data['code']??''),trim($data['grade']??'')]);
    ensure($entry!==null,'Seleccione un cargo, código y grado registrados en el catálogo de Talento Humano.');
    return ['job_id'=>$job['id'],'job_title'=>$entry['title'],'code'=>$entry['code'],'grade'=>$entry['grade'],'job_category'=>''];
}
function saveJob(array $actor,array $data): void {
    requirePermission($actor,'jobs.edit');
    atomic(function() use($actor,$data) {
        $id=(int)($data['job_id']??0);$title=trim($data['title']??'');$code=trim($data['code']??'');$grade=trim($data['grade']??'');
        $group=(string)($data['employment_group']??'regular');ensure(in_array($group,['regular','official_worker'],true),'Grupo de vinculación no válido.');
        $official=$group==='official_worker';$categoryId=$official?(int)($data['official_category_id']??0):null;
        $category=$official?row('SELECT * FROM official_job_categories WHERE id=?',[$categoryId]):null;
        ensure(!$official||$category!==null,'Seleccione una categoría de trabajador oficial.');
        ensure($title!=='' && mb_strlen($title)<=180,'Indique el nombre del cargo (hasta 180 caracteres).');
        ensure($official||($code!=='' && mb_strlen($code)<=180),'Indique el código del cargo (hasta 180 caracteres).');
        if($official)$code='';
        $old=$id?row('SELECT * FROM jobs WHERE id=?',[$id]):null;
        ensure(!$id || $old!==null,'El cargo no existe.');
        if($old)ensure(!row("SELECT person_id FROM person_employments WHERE job_id=? AND ((employment_type='Trabajador oficial')<>?) LIMIT 1",[$id,$official?1:0]),'El cargo tiene funcionarios de otro grupo de vinculación; no se puede cambiar su grupo.');
        ensure(!row('SELECT id FROM jobs WHERE title=? AND id!=?',[$title,$id]),'Ese cargo ya existe. Cada cargo pertenece a un único grupo; edite el registro existente.');
        if ($old) {
            if(!$official&&!row('SELECT id FROM job_grades WHERE job_id=?',[$id])){ensure($grade!==''&&mb_strlen($grade)<=180,'Indique el primer grado del cargo.');insert('job_grades',['job_id'=>$id,'grade'=>$grade]);}
            run('UPDATE jobs SET title=?,code=?,employment_group=?,official_category_id=? WHERE id=?',[$title,$code,$group,$categoryId,$id]);
        } else {
            ensure($official||($grade!=='' && mb_strlen($grade)<=180),'Indique el primer grado del cargo (hasta 180 caracteres).');
            $id=insert('jobs',['title'=>$title,'code'=>$code,'employment_group'=>$group,'official_category_id'=>$categoryId]);
            if(!$official)insert('job_grades',['job_id'=>$id,'grade'=>$grade]);
        }
        event((int)$actor['id'],$old?'Cargo actualizado':'Cargo registrado','Cargo '.$id.' · '.$title.' · Código '.$code);
    });
}
function saveJobGrade(array $actor,array $data): void {
    requirePermission($actor,'jobs.edit');
    atomic(function() use($actor,$data) {
        $id=(int)($data['job_id']??0);$grade=trim($data['grade']??'');
        ensure(row("SELECT id FROM jobs WHERE id=? AND employment_group='regular'",[$id])!==null,'Solo los cargos de otras vinculaciones admiten grados.');
        ensure($grade!=='' && mb_strlen($grade)<=180,'Indique el grado (hasta 180 caracteres).');
        ensure(!row('SELECT id FROM job_grades WHERE job_id=? AND grade=?',[$id,$grade]),'El grado ya está registrado para ese cargo.');
        insert('job_grades',['job_id'=>$id,'grade'=>$grade]);
        event((int)$actor['id'],'Grado registrado','Cargo '.$id.' · Grado '.$grade);
    });
}
function saveOfficialJobCategory(array $actor,array $data):void {
 requirePermission($actor,'jobs.edit');$name=trim((string)($data['name']??''));$id=(int)($data['category_id']??0);ensure($name!==''&&mb_strlen($name)<=180,'Indique la categoría (hasta 180 caracteres).');
 atomic(function()use($actor,$name,$id){
  ensure(!row('SELECT id FROM official_job_categories WHERE name=? AND id<>?',[$name,$id]),'La categoría ya existe.');
  if($id){ensure(row('SELECT id FROM official_job_categories WHERE id=?',[$id])!==null,'Categoría no encontrada.');run('UPDATE official_job_categories SET name=? WHERE id=?',[$name,$id]);}
  else $id=insert('official_job_categories',['name'=>$name]);
  event((int)$actor['id'],'Categoría de trabajador oficial guardada','Categoría '.$id.' · '.$name);
 });
}

/** Migración idempotente: no inventa códigos ni grados faltantes. Rechaza códigos contradictorios. */
function seedJobCatalog(PDO $pdo): void {
    if (!legacyUserSchema($pdo)) return;
    $ownTransaction=!$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM app_locks WHERE id=1 FOR UPDATE')->fetchColumn();
        foreach ($pdo->query('SELECT DISTINCT job_title,code,grade FROM users ORDER BY job_title,code,grade')->fetchAll() as $user) {
            $title=trim($user['job_title']);$code=trim($user['code']);$grade=trim($user['grade']);
            if ($title==='') continue;
            $find=$pdo->prepare('SELECT * FROM jobs WHERE title=?');$find->execute([$title]);$job=$find->fetch();
            if ($job && ($job['employment_group']??'regular')==='official_worker') continue;
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
        if ($ownTransaction) $pdo->commit();
    } catch(Throwable $e) { if($ownTransaction && $pdo->inTransaction())$pdo->rollBack();throw $e; }
}
