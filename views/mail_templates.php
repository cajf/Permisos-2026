<?php authorizeAdmin($u); ?>
<p class="page-intro">Personalice los asuntos y mensajes que recibe el solicitante. Los cambios se aplican a las nuevas notificaciones; los correos que ya están en cola conservan su texto.</p>
<div class="alert warning">Escriba texto plano. No incluya contraseñas, cédulas, información médica ni motivos privados. Las observaciones del aprobador se consultan dentro de la aplicación y no se agregan automáticamente al correo.</div>
<p><a class="button secondary" href="/?page=outbox">Ver cola de correos</a></p>
<?php foreach(mailTemplateDefaults() as $key=>$default):
 $template=mailTemplate($key);$failed=$error&&($_POST['template_key']??'')===$key;
 $subject=$failed?(string)($_POST['subject']??''):$template['subject'];$message=$failed?(string)($_POST['message']??''):$template['text'];
 $example=renderMailTemplate($key,['{radicado}'=>'TH-000123','{resolucion}'=>'001-2026']);
?>
<section class="panel form-panel" id="<?=e($key)?>"><h2><?=e($default['label'])?></h2>
<?php if($key==='request_approved'): ?><p>Incluye los casos en los que el jefe inmediato o el alcalde realiza la única aprobación. En esos casos no se envía el mensaje de aprobación intermedia.</p><?php endif; ?>
<form method="post"><?=csrf()?><input type="hidden" name="action" value="mail_template_save"><input type="hidden" name="template_key" value="<?=e($key)?>">
<label>Asunto<input name="subject" required maxlength="180" value="<?=e($subject)?>"></label>
<label>Mensaje<textarea name="message" required maxlength="4000" rows="5"><?=e($message)?></textarea></label>
<p class="fine">Variables disponibles: <?=e(implode(' · ',mailTemplateVariables($key)))?>. Se reemplazan automáticamente. Si no incluye {enlace} en el mensaje, se añadirá el enlace al final.</p>
<button class="button" type="submit">Guardar <?=e(mb_strtolower($default['label']))?></button></form>
<details><summary>Vista previa de la plantilla guardada</summary><p><strong><?=e($example['subject'])?></strong></p><p><?=nl2br(e($example['text']))?></p></details>
</section><?php endforeach; ?>
