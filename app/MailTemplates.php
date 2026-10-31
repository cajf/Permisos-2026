<?php
declare(strict_types=1);

function mailTemplateDefaults(): array {
    return [
        'request_registered'=>['label'=>'Registro de solicitud','subject'=>'Solicitud registrada {radicado}','text'=>'Su solicitud {radicado} fue registrada y enviada para revisión.'],
        'manager_approved'=>['label'=>'Aprobación del jefe inmediato','subject'=>'Aprobación del jefe inmediato · {radicado}','text'=>'Su solicitud {radicado} fue aprobada por el jefe inmediato y está pendiente de aprobación final.'],
        'request_approved'=>['label'=>'Aprobación final','subject'=>'Solicitud aprobada {radicado}','text'=>'Su solicitud {radicado} fue aprobada definitivamente. Ingrese a la aplicación para consultar el detalle y descargar la constancia.'],
        'request_returned'=>['label'=>'Devolución para corrección','subject'=>'Solicitud devuelta para corrección {radicado}','text'=>'Su solicitud {radicado} fue devuelta para corrección. Ingrese a la aplicación para consultar las observaciones, realizar los ajustes y enviarla nuevamente.'],
        'request_rejected'=>['label'=>'Rechazo de solicitud','subject'=>'Solicitud rechazada {radicado}','text'=>'Su solicitud {radicado} fue rechazada. Ingrese a la aplicación para consultar el motivo de la decisión.'],
        'act_approved'=>['label'=>'Aprobación de acto administrativo','subject'=>'Acto administrativo aprobado · Resolución No. {resolucion}','text'=>'Nómina aprobó el acto administrativo correspondiente a la Resolución No. {resolucion}. Puede consultar el saldo disponible en la aplicación.'],
        'act_rejected'=>['label'=>'Rechazo de acto administrativo','subject'=>'Acto administrativo rechazado · Resolución No. {resolucion}','text'=>'Nómina rechazó el acto administrativo correspondiente a la Resolución No. {resolucion}. Ingrese a la aplicación para consultar las observaciones.'],
    ];
}
function mailTemplateVariables(string $key): array {
    ensure(isset(mailTemplateDefaults()[$key]),'Tipo de correo no válido.');
    return str_starts_with($key,'act_')?['{resolucion}','{enlace}']:['{radicado}','{enlace}'];
}
function mailTemplate(string $key): array {
    $defaults=mailTemplateDefaults();ensure(isset($defaults[$key]),'Tipo de correo no válido.');
    $saved=json_decode(setting('mail_template_'.$key,''),true);
    return is_array($saved)&&is_string($saved['subject']??null)&&is_string($saved['text']??null)?array_replace($defaults[$key],$saved):$defaults[$key];
}
function saveMailTemplate(array $actor,string $key,string $subject,string $text): void {
    authorizeAdmin($actor);$variables=mailTemplateVariables($key);$subject=trim($subject);$text=trim($text);
    ensure($subject!==''&&mb_strlen($subject)<=180&&!preg_match('/[\r\n\x00-\x1F\x7F]/u',$subject),'El asunto debe tener entre 1 y 180 caracteres, sin saltos de línea ni caracteres de control.');
    ensure($text!==''&&mb_strlen($text)<=4000&&!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$text),'El mensaje debe tener entre 1 y 4.000 caracteres de texto válido.');
    preg_match_all('/\{[^{}]*\}/u',$subject."\n".$text,$matches);
    ensure(!array_diff($matches[0],$variables),'El texto contiene variables no admitidas para este correo.');
    atomic(function()use($actor,$key,$subject,$text){
        setSetting('mail_template_'.$key,json_encode(['subject'=>$subject,'text'=>$text],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        event((int)$actor['id'],'Plantilla de correo actualizada',mailTemplateDefaults()[$key]['label']);
    });
}
function renderMailTemplate(string $key,array $values): array {
    $template=mailTemplate($key);$values['{enlace}']=$GLOBALS['config']['base_url'];
    $subject=strtr($template['subject'],$values);
    $subject=mb_substr(preg_replace('/[\r\n\x00-\x1F\x7F]+/u',' ',$subject),0,255);
    $text=strtr($template['text'],$values);
    if(!str_contains($template['text'],'{enlace}'))$text.="\n\nIngrese a la aplicación:\n".$GLOBALS['config']['base_url'];
    return ['subject'=>$subject,'text'=>$text];
}
function notifyTemplate(int $userId,string $key,array $values): void {
    $mail=renderMailTemplate($key,$values);
    // Se congela únicamente el mensaje de la plantilla, nunca motivos o comentarios del trámite.
    $body=json_encode(['mail_template_version'=>1,'event'=>$key,'text'=>$mail['text']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    notify($userId,$mail['subject'],$body);
}
