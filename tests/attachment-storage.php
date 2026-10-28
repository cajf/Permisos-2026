<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/database/original-demo.php';
require ROOT.'/app/Transfer.php';require ROOT.'/app/Backup.php';
installMysqlSchema(db());restoreOriginalDemo();
$act=insert('acts',['user_id'=>5,'number'=>'DEMO-PERSISTENCIA','issued_on'=>date('Y-m-d'),'expires_on'=>date('Y-m-d'),'granted_minutes'=>60,'created_at'=>now()]);
$bytes="Archivo de prueba de persistencia\0\xff\n";$name=bin2hex(random_bytes(24));
$id=insert('attachments',['user_id'=>5,'act_id'=>$act,'original_name'=>'prueba.pdf','stored_name'=>$name,'mime'=>'application/pdf','size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'created_at'=>now()]);
insert('attachment_contents',['attachment_id'=>$id,'content_base64'=>base64_encode($bytes)]);
$attachment=row('SELECT * FROM attachments WHERE id=?',[$id]);
$path=attachmentPath($attachment);assert(file_get_contents($path)===$bytes);
unlink($path);assert(file_get_contents(attachmentPath($attachment))===$bytes);
file_put_contents($path,'Caché alterada');assert(file_get_contents(attachmentPath($attachment))===$bytes);
$backup=ROOT.'/.work/persistent-backup-'.bin2hex(random_bytes(5));
unlink($path);$manifest=createMysqlBackup(db(),uploadDir(),$backup);
assert($manifest['tables']['attachment_contents']['count']===1);
assert(hash_file('sha256',$backup.'/uploads/'.$name)===$attachment['sha256']);
unlink($path);run('UPDATE attachment_contents SET content_base64=? WHERE attachment_id=?',[base64_encode('Alterado'),$id]);
try {attachmentPath($attachment);throw new RuntimeException('Se aceptó un original alterado.');} catch (DomainException $expected) {}
run('DELETE FROM attachments WHERE id=?',[$id]);
assert((int)row('SELECT COUNT(*) n FROM attachment_contents')['n']===0);
verifyMysqlReferences(db());
echo "Persistencia MySQL verificada: reinicio de caché, integridad, respaldo y eliminación relacionada.\n";
