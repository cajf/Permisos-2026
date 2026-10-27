<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
$schema=[];
foreach(rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME') as $table){
    $name=$table['TABLE_NAME'];
    $columns=rows('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,COLUMN_KEY,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$name]);
    $references=rows('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$name]);
    $schema[]=['name'=>$name,'columns'=>$columns,'references'=>$references];
}
$path=$argv[1]??ROOT.'/.work/schema.json';file_put_contents($path,json_encode(['engine'=>db()->query('SELECT VERSION()')->fetchColumn(),'tables'=>$schema],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'Metadatos de '.count($schema)." tablas exportados sin datos personales.\n";
