<p class="page-intro">Consulta los soportes y los límites de cada tipo. Talento Humano puede seleccionar si la carga de soporte es obligatoria y debe validar las reglas marcadas como pendientes.</p>
<?php foreach(rows('SELECT * FROM categories ORDER BY id') as $c): ?>
<section class="panel form-panel policy-card">
    <div class="panel-header flush">
        <div><span class="eyebrow"><?=e(familyLabel($c['family']))?></span><h2><?=e($c['name'])?></h2></div>
        <span class="badge <?=(int)$c['policy_confirmed']?'badge-approved':'badge-returned'?>"><?=(int)$c['policy_confirmed']?'Regla configurada':'Validación pendiente'?></span>
    </div>
    <?php if(hasRole($u,'admin')): ?>
    <form method="post" data-policy-form>
        <?=csrf()?><input type="hidden" name="action" value="policy"><input type="hidden" name="id" value="<?=(int)$c['id']?>">
        <label class="check-label"><input type="checkbox" name="supports_required" value="1" <?=(int)$c['supports_required']?'checked':''?>> Exigir al menos un soporte</label>
        <p class="fine">Si desmarcas esta opción, el funcionario podrá enviar la solicitud sin adjuntos. Los soportes que decida cargar conservarán las validaciones de formato y tamaño.</p>
        <div class="form-grid">
            <label>Documentos de soporte<textarea name="supports" <?=(int)$c['supports_required']?'required':''?> rows="3"><?=e($c['supports'])?></textarea></label>
            <label>Condiciones y frecuencia<textarea name="notes" rows="3"><?=e($c['notes'])?></textarea></label>
            <label>Máximo de horas por solicitud<input name="max_hours" type="number" step="0.05" min="0.05" value="<?=$c['max_minutes']!==null?e($c['max_minutes']/60):''?>" <?=$c['slug']==='ordinario'?'disabled':''?>><small><?=$c['slug']==='ordinario'?'El cupo semestral fijo es de 25 h 30 min.':'Vacío: sin límite numérico configurado.'?></small></label>
            <label class="check-label"><input type="checkbox" name="policy_confirmed" <?=(int)$c['policy_confirmed']?'checked':''?>> Regla revisada por Talento Humano</label>
        </div>
        <button class="button secondary small">Guardar regla</button>
    </form>
    <?php else: ?>
    <p><strong>Carga de soporte:</strong> <?=(int)$c['supports_required']?'Obligatoria':'Opcional'?>.</p>
    <?php if($c['supports']!==''): ?><p><strong>Documentos de soporte:</strong> <?=e($c['supports'])?></p><?php endif; ?>
    <p><?=e($c['notes'])?></p>
    <?php if($c['max_minutes']!==null && (int)$c['policy_confirmed']): ?><p>Máximo por solicitud: <?=e(hours((int)$c['max_minutes']))?></p><?php endif; ?>
    <?php endif; ?>
</section>
<?php endforeach; ?>
