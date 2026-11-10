<?php
declare(strict_types=1);

function fixtureUser(array $fields): int {
    $create=function() use($fields): int {
        $id=createDemoIdentity($fields+['roles'=>'[]']);
        $employment=array_intersect_key($fields,array_flip(['manager_id','deputy_manager_id']));
        $account=array_intersect_key($fields,array_flip(['active','failures','locked_until']));
        return saveUserIdentity($id,[],$employment,$account);
    };
    return db()->inTransaction()?$create():atomic($create);
}

function fixtureOfficialWorker(int $id,?string $birth=null): void {
    atomic(function() use($id,$birth) {
        run("INSERT INTO official_job_categories(name) VALUES('Categoría de prueba') ON DUPLICATE KEY UPDATE id=id");
        $category=row("SELECT id FROM official_job_categories WHERE name='Categoría de prueba'");
        $job=insert('jobs',['title'=>'Cargo oficial de prueba '.$id,'code'=>'','employment_group'=>'official_worker','official_category_id'=>$category['id']]);
        saveUserIdentity($id,$birth?['birth_date'=>$birth]:[],['employment_type'=>'Trabajador oficial','job_id'=>$job,'grade'=>null],[]);
    });
}
