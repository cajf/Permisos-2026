<?php
declare(strict_types=1);

function createMysqlBackup(PDO $pdo,string $uploads,string $destination): array {
    if (is_dir($destination)) throw new RuntimeException('El directorio de respaldo ya existe.');
    if (!mkdir($destination.'/uploads',0700,true)) throw new RuntimeException('No se puede crear el respaldo.');
    $handle=fopen($destination.'/database.sql.partial','wb');if (!$handle) throw new RuntimeException('No se puede escribir el respaldo.');
    $manifest=['engine'=>$pdo->query('SELECT VERSION()')->fetchColumn(),'created_at'=>date(DATE_ATOM),'tables'=>[],'files'=>[]];
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM app_locks WHERE id=1 FOR UPDATE')->fetchColumn();
        fwrite($handle,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE=".$pdo->quote($pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn()).";\n");
        foreach (array_merge(applicationTables(),['app_locks']) as $table) {
            if (in_array($table,['attachment_contents','jobs','job_grades'],true) && !tableExists($table,$pdo)) continue;
            $quoted=mysqlIdentifier($table);$create=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
            fwrite($handle,$create.";\n");
            $keys=array_column($pdo->query("SHOW KEYS FROM ".$quoted." WHERE Key_name='PRIMARY'")->fetchAll(),'Column_name');
            $order=implode(',',array_map('mysqlIdentifier',$keys));$records=$pdo->query('SELECT * FROM '.$quoted.' ORDER BY '.$order)->fetchAll();
            $manifest['tables'][$table]=['count'=>count($records),'digest'=>canonicalRows($records)];
            foreach ($records as $record) {
                $columns=implode(',',array_map('mysqlIdentifier',array_keys($record)));
                $values=implode(',',array_map(fn($value)=>$value===null?'NULL':$pdo->quote((string)$value),array_values($record)));
                fwrite($handle,'INSERT INTO '.$quoted.' ('.$columns.') VALUES('.$values.");\n");
            }
        }
        foreach ($pdo->query('SELECT id,stored_name,sha256,size FROM attachments')->fetchAll() as $file) {
            $name=$file['stored_name'];if (!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$name)) throw new RuntimeException('Nombre interno de soporte inválido.');
            $source=attachmentPath($file,$pdo,$uploads);
            if (!is_file($source) || hash_file('sha256',$source)!==$file['sha256']) throw new RuntimeException('Falta un soporte o no coincide su integridad.');
            if (!copy($source,$destination.'/uploads/'.$name)) throw new RuntimeException('No se pudo copiar un soporte.');
            $manifest['files'][$name]=$file['sha256'];
        }
        fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($handle);$handle=null;$pdo->commit();
        rename($destination.'/database.sql.partial',$destination.'/database.sql');
        $manifest['sql_sha256']=hash_file('sha256',$destination.'/database.sql');
        file_put_contents($destination.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return $manifest;
    } catch (Throwable $error) {if ($pdo->inTransaction()) $pdo->rollBack();if (is_resource($handle)) fclose($handle);throw $error;}
}
function restoreMysqlBackup(PDO $pdo,string $backup,string $uploads): void {
    $manifest=json_decode(file_get_contents($backup.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if (hash_file('sha256',$backup.'/database.sql')!==$manifest['sql_sha256']) throw new RuntimeException('El SQL del respaldo no coincide con su comprobación.');
    if ($pdo->query('SHOW TABLES')->fetchAll()!==[]) throw new RuntimeException('Restaure en una base vacía. No se reemplazan tablas existentes.');
    foreach ($manifest['files'] as $name=>$checksum) {
        if (!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$name) || !is_file($backup.'/uploads/'.$name) || hash_file('sha256',$backup.'/uploads/'.$name)!==$checksum) throw new RuntimeException('Un soporte del respaldo no supera la comprobación.');
        if (is_file($uploads.'/'.$name) && hash_file('sha256',$uploads.'/'.$name)!==$checksum) throw new RuntimeException('Un soporte del destino es diferente al respaldo.');
    }
    // El SQL procede exclusivamente de un respaldo cuyo SHA-256 acaba de comprobarse.
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
    try {$statement=$pdo->query(file_get_contents($backup.'/database.sql'));while ($statement->nextRowset()) {}$statement->closeCursor();}
    finally {$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);}
    verifyMysqlReferences($pdo);
    foreach ($manifest['tables'] as $table=>$expected) {
        $quoted=mysqlIdentifier($table);$keys=array_column($pdo->query("SHOW KEYS FROM ".$quoted." WHERE Key_name='PRIMARY'")->fetchAll(),'Column_name');
        $records=$pdo->query('SELECT * FROM '.$quoted.' ORDER BY '.implode(',',array_map('mysqlIdentifier',$keys)))->fetchAll();
        if (count($records)!==$expected['count'] || canonicalRows($records)!==$expected['digest']) throw new RuntimeException('No coincide la tabla restaurada '.$table.'.');
    }
    if (!is_dir($uploads) && !mkdir($uploads,0700,true)) throw new RuntimeException('No se puede crear el destino de soportes.');
    foreach ($manifest['files'] as $name=>$checksum) if (!is_file($uploads.'/'.$name) && !copy($backup.'/uploads/'.$name,$uploads.'/'.$name)) throw new RuntimeException('No se pudo restaurar un soporte.');
}
