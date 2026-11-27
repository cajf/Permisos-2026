<?php if(ElectoralCertificate::SHOW_TEMPORARY_DETAILS): ?>
<section class="alert warning" aria-label="Información temporal de lectura OCR" role="status">
<h3>Texto leído por OCR · Mensaje temporal</h3>
<p>Se muestra el texto capturado en cada campo, incluso si contiene errores o tiene baja confianza. Estos textos son de diagnóstico; no equivalen a datos validados ni comprueban la autenticidad del documento.</p>
<?php if(empty($electoralReport['documents'])): ?>
<p>No se pudieron identificar datos del documento, o esta solicitud no conserva un resultado de lectura.</p>
<?php else: ?>
<ul class="plain-list">
<?php foreach($electoralReport['documents'] as $index=>$document): ?>
<li><strong><?=e($document['file']??('Documento '.($index+1)))?> · Página procesada <?=($index+1)?></strong>
<?php foreach(['certificate_type'=>'Tipo de certificado','document_number'=>'Cédula','election_date'=>'Fecha de la elección'] as $field=>$label):
    $values=$document['read_fields'][$field]??($field==='document_number'?($document['documents']??[]):($field==='election_date'?($document['dates']??[]):[])); ?>
<div><b><?=e($label)?> · Texto leído:</b> <?=e($values?implode(' / ',$values):'Sin texto capturado para este campo')?></div>
<?php endforeach; ?>
<?php if(count($document['documents']??[])>1 || count($document['dates']??[])>1): ?><div><b>Lectura ambigua:</b> se encontraron varios valores; se requiere revisión manual.</div><?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>
<?php endif; ?>
