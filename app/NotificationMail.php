<?php
declare(strict_types=1);

function notificationMailConfig(): array {return $GLOBALS['config']['mail'];}
function notificationMailIssues(): array {
    $c=notificationMailConfig();$issues=[];
    if(!$c['enabled'])$issues[]='Envío desactivado.';
    if($c['api_key']===''||preg_match('/[\r\n]/',$c['api_key']))$issues[]='Falta una clave API válida de Brevo.';
    if(!notificationMailbox($c['from']))$issues[]='Falta el remitente real verificado en Brevo.';
    $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$c['start_at']);
    if(!$date||$date->format('Y-m-d H:i:s')!==$c['start_at'])$issues[]='Falta la fecha fija de inicio de envíos (hora de Bogotá).';
    if(!function_exists('curl_init'))$issues[]='PHP requiere la extensión cURL.';
    $url=parse_url($GLOBALS['config']['base_url']);
    if(!$url||!in_array($url['scheme']??'',['http','https'],true)||empty($url['host']))$issues[]='Falta la URL válida de la aplicación.';
    return $issues;
}
function notificationMailbox(string $address): bool {
    if(!filter_var($address,FILTER_VALIDATE_EMAIL))return false;
    $domain=strtolower(substr(strrchr($address,'@'),1));
    return !preg_match('/(?:^|\.)(invalid|test|example|localhost)$/D',$domain)&&!in_array($domain,['example.com','example.org','example.net'],true);
}
function notificationPayload(array $item): array {
    $c=notificationMailConfig();ensure(notificationMailbox($item['recipient']),'Destinatario de demostración o dirección no válida.');
    // No se transmiten comentarios de aprobación, motivos, soportes ni datos de salud.
    $text="Tiene una notificación de la aplicación de permisos, compensatorios y licencias.\n\n".$item['subject']."\n\nIngrese con su usuario para consultar el detalle:\n".$GLOBALS['config']['base_url'];
    $snapshot=json_decode($item['body']??'',true);
    if(is_array($snapshot)&&($snapshot['mail_template_version']??null)===1&&isset(mailTemplateDefaults()[$snapshot['event']??''])&&is_string($snapshot['text']??null))$text=$snapshot['text'];
    return ['sender'=>['email'=>$c['from'],'name'=>$c['from_name']],'to'=>[['email'=>$item['recipient']]],'subject'=>$item['subject'],'textContent'=>$text,'htmlContent'=>'<p>'.nl2br(e($text)).'</p>'];
}
class NotificationDeliveryError extends RuntimeException {
    public function __construct(string $message,public readonly string $deliveryState='blocked',public readonly bool $stop=true){parent::__construct($message);}
}
function notificationBrevoSend(array $payload): string {
    $curl=curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['accept: application/json','content-type: application/json','api-key: '.notificationMailConfig()['api_key']],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    if(PHP_OS_FAMILY==='Windows'&&defined('CURLSSLOPT_NATIVE_CA'))curl_setopt($curl,CURLOPT_SSL_OPTIONS,CURLSSLOPT_NATIVE_CA);
    $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    return notificationBrevoResponse($body,$status);
}
function notificationBrevoResponse(string|false $body,int $status): string {
    // Una interrupción puede ocurrir después de que Brevo haya aceptado el correo: no reenviar automáticamente.
    if($body===false)throw new NotificationDeliveryError('No se pudo confirmar la respuesta de Brevo. Revise sus registros antes de reenviar.','uncertain');
    $data=json_decode($body,true);
    if($status===201&&is_array($data)&&!empty($data['messageId'])&&is_string($data['messageId']))return mb_substr($data['messageId'],0,255);
    if($status===429)throw new NotificationDeliveryError('Brevo limitó el envío. Se reintentará después de la pausa.','failed');
    if($status>=500||($status>=200&&$status<300))throw new NotificationDeliveryError('Brevo devolvió una respuesta no concluyente. Revise sus registros antes de reenviar.','uncertain');
    throw new NotificationDeliveryError('Brevo rechazó el envío (HTTP '.$status.'). Revise clave, activación, remitente y destinatario.');
}
function processNotificationMail(int $limit=5,?callable $transport=null): array {
    $result=['accepted'=>0,'blocked'=>0,'failed'=>0,'uncertain'=>0,'paused'=>false];
    if(notificationMailIssues())return $result;
    $lock='th-mail-'.substr(hash('sha256',databaseIdentity()),0,40);
    if(!(int)row('SELECT GET_LOCK(?,0) acquired',[$lock])['acquired'])return $result;
    try {
        if((int)setting('mail_pause_until','0')>time()){$result['paused']=true;return $result;}
        $c=notificationMailConfig();$budgetKey='mail_attempts_'.date('Y-m-d');$budget=(int)setting($budgetKey,'0');
        $quota=max(1,min(300,(int)$c['daily_limit']));$limit=max(1,min(10,$limit));
        foreach(rows("SELECT * FROM outbox WHERE status IN ('pending','failed') AND attempts<5 AND created_at>=? ORDER BY id LIMIT ".$limit,[$c['start_at']]) as $item){
            if($budget>=$quota){$result['paused']=true;break;}
            if(!notificationMailbox($item['recipient'])){run("UPDATE outbox SET status='blocked',last_error=? WHERE id=? AND status IN ('pending','failed')",['Destinatario de demostración o dirección no válida.',$item['id']]);$result['blocked']++;continue;}
            if(!run("UPDATE outbox SET status='sending',attempts=attempts+1 WHERE id=? AND status IN ('pending','failed')",[$item['id']]))continue;
            try {
                setSetting($budgetKey,(string)++$budget);
                $id=($transport??'notificationBrevoSend')(notificationPayload($item));
                if(!is_string($id)||$id==='')throw new NotificationDeliveryError('Falta confirmación de aceptación.','uncertain');
                setSetting('mail_receipt_'.$item['id'],json_encode(['provider'=>'brevo','id'=>$id,'accepted_at'=>now()],JSON_THROW_ON_ERROR));
                run("UPDATE outbox SET status='sent',sent_at=?,last_error=NULL WHERE id=?",[now(),$item['id']]);$result['accepted']++;
            }catch(NotificationDeliveryError $e){
                run('UPDATE outbox SET status=?,last_error=? WHERE id=?',[$e->deliveryState,$e->getMessage(),$item['id']]);$result[$e->deliveryState]++;
                if($e->stop){setSetting('mail_pause_until',(string)(time()+900));break;}
            }catch(Throwable){
                run("UPDATE outbox SET status='uncertain',last_error=? WHERE id=?",['No se pudo completar el registro del envío. Revise Brevo antes de reenviar.',$item['id']]);$result['uncertain']++;break;
            }
        }
    }finally{run('SELECT RELEASE_LOCK(?)',[$lock]);}
    return $result;
}
