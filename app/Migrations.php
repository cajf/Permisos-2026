<?php
declare(strict_types=1);

/** La instalación y actualización de MySQL se ejecutan explícitamente con credenciales de administración. */
function migrateDatabase(PDO $pdo): void {
    installMysqlSchema($pdo);
    migrateOfficialJobCategories($pdo);
    seedJobCatalog($pdo);
    verifyMysqlReferences($pdo);
}
function migrateOfficialJobCategories(PDO $pdo):void {
    installMysqlSchema($pdo);
    foreach(['jobs'=>['employment_group'=>"VARCHAR(30) NOT NULL DEFAULT 'regular'",'official_category_id'=>'BIGINT UNSIGNED NULL'],'users'=>['job_category'=>"VARCHAR(180) NOT NULL DEFAULT ''"]] as $table=>$columns)foreach($columns as $column=>$definition){
        $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$s->execute([$table,$column]);if(!(int)$s->fetchColumn())$pdo->exec('ALTER TABLE '.mysqlIdentifier($table).' ADD COLUMN '.mysqlIdentifier($column).' '.$definition);
    }
    $s=$pdo->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='jobs' AND constraint_name='fk_job_official_category'");
    if(!(int)$s->fetchColumn())$pdo->exec('ALTER TABLE jobs ADD CONSTRAINT fk_job_official_category FOREIGN KEY (official_category_id) REFERENCES official_job_categories(id)');
}
