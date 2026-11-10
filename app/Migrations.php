<?php
declare(strict_types=1);

/** La instalación y actualización de MySQL se ejecutan explícitamente con credenciales de administración. */
function migrateDatabase(PDO $pdo): void {
    if (legacyUserSchema($pdo)) migrateUserIdentity($pdo);
    else installMysqlSchema($pdo);
    verifyMysqlReferences($pdo);
}
function migrateOfficialJobCategories(PDO $pdo):void {
    if (!legacyUserSchema($pdo)) {installMysqlSchema($pdo);return;}
    installMysqlSchema($pdo,true);
    foreach(['jobs'=>['employment_group'=>"VARCHAR(30) NOT NULL DEFAULT 'regular'",'official_category_id'=>'BIGINT UNSIGNED NULL'],'users'=>['job_category'=>"VARCHAR(180) NOT NULL DEFAULT ''"]] as $table=>$columns)foreach($columns as $column=>$definition){
        $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$s->execute([$table,$column]);if(!(int)$s->fetchColumn())$pdo->exec('ALTER TABLE '.mysqlIdentifier($table).' ADD COLUMN '.mysqlIdentifier($column).' '.$definition);
    }
    $s=$pdo->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='jobs' AND constraint_name='fk_job_official_category'");
    if(!(int)$s->fetchColumn())$pdo->exec('ALTER TABLE jobs ADD CONSTRAINT fk_job_official_category FOREIGN KEY (official_category_id) REFERENCES official_job_categories(id)');
}

/** Requires the application to be stopped during the schema cutover. */
function migrateUserIdentity(PDO $pdo): void {
    if (!legacyUserSchema($pdo)) {installMysqlSchema($pdo);return;}
    migrateOfficialJobCategories($pdo);
    [, $identity, , $profiles]=mysqlSchemaParts();
    executeMysqlSchema($pdo,$identity);

    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM app_locks WHERE id=1 FOR UPDATE')->fetchColumn();
        seedJobCatalog($pdo);
        foreach (['user_roles','person_employments','persons'] as $table) $pdo->exec('DELETE FROM '.mysqlIdentifier($table));
        $users=$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
        $jobLookup=$pdo->prepare('SELECT j.*,c.name AS category FROM jobs j LEFT JOIN official_job_categories c ON c.id=j.official_category_id WHERE j.title=?');
        $personInsert=$pdo->prepare('INSERT INTO persons(id,name,identification,email,birth_date) VALUES(?,?,?,?,?)');
        $employmentInsert=$pdo->prepare('INSERT INTO person_employments(person_id,employment_type,department_id,job_id,grade,manager_id,deputy_manager_id,high_rank) VALUES(?,?,?,?,?,?,?,?)');
        $roleInsert=$pdo->prepare('INSERT INTO user_roles(user_id,role_code) VALUES(?,?)');
        $roleCount=0;
        foreach ($users as $user) {
            $id=$user['id'];
            $jobLookup->execute([trim($user['job_title'])]);
            $job=$jobLookup->fetch();
            $official=$user['appointment_type']==='Trabajador oficial';
            if (!$job || ($job['employment_group']==='official_worker')!==$official
                || ($official && (!$job['category'] || ($user['job_category']!=='' && $user['job_category']!==$job['category'])))
                || ($official && ($user['grade']!=='' || $user['code']!==''))
                || (!$official && $user['job_category']!=='')) {
                throw new RuntimeException("El cargo o la categoría de la cuenta $id no coincide con el catálogo de su vinculación.");
            }
            $personInsert->execute([$id,$user['name'],$user['identification'],$user['email'],$user['birth_date']]);
            $employmentInsert->execute([$id,$user['appointment_type'],$user['department_id'],$job['id'],$official?null:trim($user['grade']),$user['manager_id'],$user['deputy_manager_id'],$user['high_rank']]);
            $roles=json_decode($user['roles'],true,512,JSON_THROW_ON_ERROR);
            if (!is_array($roles) || !array_is_list($roles) || count(array_filter($roles,'is_string'))!==count($roles)) throw new RuntimeException("Los roles de la cuenta $id no forman una lista válida.");
            foreach (array_unique($roles) as $role) {$roleInsert->execute([$id,$role]);$roleCount++;}
        }
        foreach (['persons'=>count($users),'person_employments'=>count($users),'user_roles'=>$roleCount] as $table=>$expected) {
            if ((int)$pdo->query('SELECT COUNT(*) FROM '.mysqlIdentifier($table))->fetchColumn()!==$expected) throw new RuntimeException("No coincide el recuento de $table.");
        }
        verifyMysqlReferences($pdo);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    $checks=$pdo->query("SELECT tc.CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME='users' AND cc.CHECK_CLAUSE LIKE '%roles%'")->fetchAll(PDO::FETCH_COLUMN);
    $alter=array_map(fn($check)=>'DROP CHECK '.mysqlIdentifier($check),$checks);
    foreach (['department','manager','deputy'] as $constraint) $alter[]='DROP FOREIGN KEY fk_users_'.$constraint;
    foreach (['name','identification','email','birth_date','job_title','code','grade','job_category','appointment_type','department_id','manager_id','deputy_manager_id','high_rank','roles'] as $column) $alter[]='DROP COLUMN '.mysqlIdentifier($column);
    $alter[]='ADD CONSTRAINT fk_users_person FOREIGN KEY (id) REFERENCES persons(id)';
    $pdo->exec('ALTER TABLE users '.implode(', ',$alter));
    executeMysqlSchema($pdo,$profiles);
    verifyMysqlReferences($pdo);
}
