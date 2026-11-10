<?php
declare(strict_types=1);

function databaseIdentity(): string {
    $c=$GLOBALS['config']['mysql'];return $c['host'].':'.$c['port'].'/'.$c['database'];
}
function mysqlConnection(array $config,bool $selectDatabase=true,bool $multipleStatements=false): PDO {
    foreach (['host','database'] as $key) if (str_contains((string)$config[$key],';')) throw new RuntimeException('Configuración MySQL no válida.');
    if (!preg_match('/^[A-Za-z0-9_]+$/D',$config['database'])) throw new RuntimeException('El nombre de la base MySQL solo admite letras, números y guiones bajos.');
    $dsn='mysql:host='.$config['host'].';port='.(int)$config['port'].';charset=utf8mb4'.($selectDatabase?';dbname='.$config['database']:'');
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>$multipleStatements];
    if (!empty($config['ssl_ca'])) {
        if (!is_file($config['ssl_ca'])) throw new RuntimeException('No se encuentra el certificado CA de MySQL.');
        $options[PDO::MYSQL_ATTR_SSL_CA]=$config['ssl_ca'];
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;
    }
    $pdo=new PDO($dsn,$config['username'],$config['password'],$options);
    if (!empty($config['ssl_ca']) && empty($pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM)[1])) throw new RuntimeException('MySQL debe usar una conexión cifrada con certificado verificado.');
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
function mysqlSchemaParts(): array {
    $schema=file_get_contents(__DIR__.'/../database/schema.sql');
    [$catalog,$identity,$application,$profiles]=preg_split('/-- (?:BEGIN IDENTITY|END IDENTITY|USER PROFILES)/',$schema);
    return [$catalog,$identity,$application,$profiles];
}
function executeMysqlSchema(PDO $pdo,string $schema): void {
    foreach (explode(';',$schema) as $statement) if (trim($statement)!=='') $pdo->exec($statement);
}
function installMysqlSchema(PDO $pdo,bool $legacy=false): void {
    [$catalog,$identity,$application,$profiles]=mysqlSchemaParts();
    if ($legacy) {
        $identity=file_get_contents(__DIR__.'/../database/legacy/mysql-users.sql');
        $profiles='';
    }
    executeMysqlSchema($pdo,$catalog.$identity.$application.$profiles);
}
function legacyUserSchema(PDO $pdo): bool {
    return (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='identification'")->fetchColumn();
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
    $references=$pdo->query('SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY ORDINAL_POSITION')->fetchAll();
    $constraints=[];
    foreach ($references as $reference) $constraints[$reference['TABLE_NAME']][$reference['CONSTRAINT_NAME']][]=$reference;
    foreach ($constraints as $table=>$foreignKeys) foreach ($foreignKeys as $name=>$columns) {
        $join=[];$required=[];
        foreach ($columns as $reference) {
            $column='child.'.mysqlIdentifier($reference['COLUMN_NAME']);
            $key='parent.'.mysqlIdentifier($reference['REFERENCED_COLUMN_NAME']);
            $join[]="$key=$column";
            $required[]="$column IS NOT NULL";
        }
        $parent=mysqlIdentifier($columns[0]['REFERENCED_TABLE_NAME']);
        $firstKey=mysqlIdentifier($columns[0]['REFERENCED_COLUMN_NAME']);
        $sql='SELECT 1 FROM '.mysqlIdentifier($table).' child LEFT JOIN '.$parent.' parent ON '.implode(' AND ',$join).' WHERE '.implode(' AND ',$required)." AND parent.$firstKey IS NULL LIMIT 1";
        if ($pdo->query($sql)->fetchColumn()) throw new RuntimeException("Referencia inválida en $table ($name).");
    }
}
