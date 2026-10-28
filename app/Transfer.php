<?php
declare(strict_types=1);

function applicationTables(): array {
    return ['departments','users','settings','categories','holidays','schedules','acts','requests','request_slots','designations','approvals','allocations','attachments','attachment_contents','events','outbox','balance_snapshots'];
}
function canonicalRows(array $records): string {
    foreach ($records as &$record) {ksort($record);foreach ($record as &$value) if ($value!==null) $value=(string)$value;unset($value);}unset($record);
    return hash('sha256',json_encode($records,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function transferSqliteToMysql(PDO $source,PDO $target,string $sourceUploads,string $targetUploads): array {
    if ($source->query('PRAGMA foreign_key_check')->fetchAll()!==[]) throw new RuntimeException('La base de origen tiene referencias inválidas. No se importó ningún registro.');
    $target->beginTransaction();$copied=[];$counts=[];$files=[];
    try {
        $target->query('SELECT id FROM app_locks WHERE id=1 FOR UPDATE')->fetchColumn();
        foreach (applicationTables() as $table) if ((int)$target->query('SELECT COUNT(*) FROM '.mysqlIdentifier($table))->fetchColumn()!==0) throw new RuntimeException('La base de destino debe estar vacía. No se sobrescriben datos existentes.');
        $source->beginTransaction();
        foreach ($source->query('SELECT * FROM attachments')->fetchAll(PDO::FETCH_ASSOC) as $attachment) {
            $stored=$attachment['stored_name'];
            if (!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$stored)) throw new RuntimeException('Nombre interno de soporte no válido.');
            $path=$sourceUploads.'/'.$stored;
            if (!is_file($path) || hash_file('sha256',$path)!==$attachment['sha256']) throw new RuntimeException('Falta un soporte o no coincide su integridad: adjunto '.$attachment['id'].'.');
            $files[]=$attachment;
        }
        if (!is_dir($targetUploads) && !mkdir($targetUploads,0700,true)) throw new RuntimeException('No se puede crear el directorio de soportes de destino.');
        $target->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (applicationTables() as $table) {
            $info=$source->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
            if (!$info && $table==='attachment_contents') {$counts[$table]=0;continue;}
            if (!$info) throw new RuntimeException('Falta una tabla del origen: '.$table.'.');
            $columns=array_column($info,'name');$primary=array_column(array_filter($info,fn($field)=>(int)$field['pk']>0),'name');
            $order=' ORDER BY '.implode(',',array_map('mysqlIdentifier',$primary));
            $records=$source->query('SELECT * FROM '.mysqlIdentifier($table).$order)->fetchAll(PDO::FETCH_ASSOC);
            $fields=implode(',',array_map('mysqlIdentifier',$columns));
            $insert=$target->prepare('INSERT INTO '.mysqlIdentifier($table).' ('.$fields.') VALUES('.implode(',',array_fill(0,count($columns),'?')).')');
            foreach ($records as $record) $insert->execute(array_values($record));
            $destination=$target->query('SELECT '.$fields.' FROM '.mysqlIdentifier($table).$order)->fetchAll(PDO::FETCH_ASSOC);
            if (count($records)!==count($destination) || canonicalRows($records)!==canonicalRows($destination)) throw new RuntimeException('No coincide la verificación de la tabla '.$table.'.');
            $counts[$table]=count($records);
        }
        verifyMysqlReferences($target);
        foreach ($files as $attachment) {
            $destination=$targetUploads.'/'.$attachment['stored_name'];
            if (is_file($destination)) {
                if (hash_file('sha256',$destination)!==$attachment['sha256']) throw new RuntimeException('Un archivo del destino no coincide con su origen.');
            } else {
                if (!copy($sourceUploads.'/'.$attachment['stored_name'],$destination)) throw new RuntimeException('No se pudo trasladar un soporte.');
                $copied[]=$destination;
            }
        }
        $target->exec('SET FOREIGN_KEY_CHECKS=1');$target->commit();$source->commit();
        return ['tables'=>$counts,'files_verified'=>count($files),'files_copied'=>count($copied),'completed_at'=>date(DATE_ATOM)];
    } catch (Throwable $error) {
        if ($target->inTransaction()) $target->rollBack();
        if ($source->inTransaction()) $source->rollBack();
        $target->exec('SET FOREIGN_KEY_CHECKS=1');
        foreach ($copied as $path) if (is_file($path)) unlink($path);
        throw $error;
    }
}
