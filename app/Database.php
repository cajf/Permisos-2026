<?php
declare(strict_types=1);

function databaseIdentity(): string {
    $c=$GLOBALS['config']['mysql'];return $c['host'].':'.$c['port'].'/'.$c['database'];
}
function mysqlConnection(array $config,bool $selectDatabase=true,bool $multipleStatements=false): PDO {
    foreach (['host','database'] as $key) if (str_contains((string)$config[$key],';')) throw new RuntimeException('Configuración MySQL no válida.');
    if (!preg_match('/^[A-Za-z0-9_]+$/D',$config['database'])) throw new RuntimeException('El nombre de la base MySQL solo admite letras, números y guiones bajos.');
    $dsn='mysql:host='.$config['host'].';port='.(int)$config['port'].';charset=utf8mb4'.($selectDatabase?';dbname='.$config['database']:'');
    $pdo=new PDO($dsn,$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>$multipleStatements]);
    $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
    if (stripos($version,'mariadb')!==false || version_compare($version,'8.4.0','<')) throw new RuntimeException('Se requiere MySQL 8.4 o superior. La instalación local usa MySQL 8.4 LTS.');
    $pdo->exec("SET time_zone='-05:00'");
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    return $pdo;
}
function tableExists(string $table,?PDO $pdo=null): bool {
    $query=($pdo??db())->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$query->execute([$table]);return (int)$query->fetchColumn()>0;
}
function mysqlIdentifier(string $name): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$name)) throw new RuntimeException('Identificador SQL no válido.');return '`'.$name.'`';
}
function installMysqlSchema(PDO $pdo): void {
    foreach (explode(';',file_get_contents(__DIR__.'/../database/schema.sql')) as $statement) if (trim($statement)!=='') $pdo->exec($statement);
}
function administrativeMysqlConnection(): PDO {
    $configuration=$GLOBALS['config']['mysql'];$path=__DIR__.'/../storage/mysql/admin.php';
    if (is_file($path)) {
        $admin=require $path;
        if ($admin['host']===$configuration['host'] && (int)$admin['port']===(int)$configuration['port']) $configuration=array_replace($admin,['database'=>$configuration['database']]);
    }
    return mysqlConnection($configuration);
}
function verifyMysqlReferences(PDO $pdo): void {
    $references=$pdo->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll();
    foreach ($references as $reference) {
        $table=mysqlIdentifier($reference['TABLE_NAME']);$column=mysqlIdentifier($reference['COLUMN_NAME']);$parent=mysqlIdentifier($reference['REFERENCED_TABLE_NAME']);$key=mysqlIdentifier($reference['REFERENCED_COLUMN_NAME']);
        if ($pdo->query('SELECT 1 FROM '.$table.' child LEFT JOIN '.$parent.' parent ON parent.'.$key.'=child.'.$column.' WHERE child.'.$column.' IS NOT NULL AND parent.'.$key.' IS NULL LIMIT 1')->fetchColumn()) throw new RuntimeException('Referencia inválida en '.$reference['TABLE_NAME'].'.'.$reference['COLUMN_NAME'].'.');
    }
}
