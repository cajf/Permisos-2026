<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
if(in_array('--smtp',$argv,true)){require __DIR__.'/mail-worker-smtp.php';exit;}
require __DIR__.'/../app/bootstrap.php';
$issues=notificationMailIssues();
if(in_array('--ready',$argv,true)){echo $issues?'0':'1';exit;}
if($issues){echo implode("\n",$issues)."\nLa cola permanece pendiente.\n";exit(in_array('--check',$argv,true)?1:0);}
if(in_array('--check',$argv,true)){echo "API Brevo configurada. No se realizó ningún envío.\n";exit;}
do {
 try{echo json_encode(processNotificationMail(5),JSON_UNESCAPED_UNICODE).PHP_EOL;}
 catch(Throwable){error_log('No se pudo procesar la cola de notificaciones.');}
 if(!in_array('--loop',$argv,true))break;
 sleep(30);
}while(true);
