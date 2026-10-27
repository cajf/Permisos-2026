<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$job=json_decode(base64_decode($argv[1]),true,512,JSON_THROW_ON_ERROR);
file_put_contents($job['ready'],'ready');$deadline=microtime(true)+15;
while (!is_file($job['go'])) {if(microtime(true)>$deadline)throw new RuntimeException('No se abrió la barrera de prueba.');usleep(10000);}
try {
    $actor=row('SELECT u.*,d.name department FROM users u JOIN departments d ON d.id=u.department_id WHERE u.id=?',[$job['actor']]);$workflow=new Workflow();
    if ($job['action']==='submit') $id=$workflow->submit($actor,$job['input']);
    else {$workflow->review($actor,$job['request'],'approved','Aprobación simultánea de prueba');$id=$job['request'];}
    echo json_encode(['ok'=>true,'id'=>$id]);
} catch (DomainException $error) {echo json_encode(['ok'=>false,'message'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);}
