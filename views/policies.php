<p class="page-intro">Consulta los soportes y los límites de cada tipo. Talento Humano puede seleccionar si la carga de soporte es obligatoria y debe validar las reglas marcadas como pendientes.</p>
<?php $year=(int)date('Y'); foreach(rows('SELECT * FROM categories ORDER BY id') as $c):
    $annual=in_array($c['slug'],AnnualLeave::TYPES,true);
    $annualLimit=$annual?AnnualLeave::limit($c['slug'],$year):null;
?>
<section class="panel form-panel policy-card">
    <div class="panel-header flush">
        <div><span class="eyebrow"><?=e(familyLabel($c['family']))?></span><h2><?=e($c['name'])?></h2></div>
        <span class="badge <?=(int)$c['policy_confirmed']?'badge-approved':'badge-returned'?>"><?=(int)$c['policy_confirmed']?'Regla configurada':'Validación pendiente'?></span>
    </div>
    <?php if(can($u,'policies.edit')): ?>
    <form method="post" data-policy-form>
        <?=csrf()?><input type="hidden" name="action" value="policy"><input type="hidden" name="id" value="<?=(int)$c['id']?>">
        <label class="check-label"><input type="checkbox" name="supports_required" value="1" <?=(int)$c['supports_required']?'checked':''?>> Exigir al menos un soporte</label>
        <p class="fine">Si desmarcas esta opción, el funcionario podrá enviar la solicitud sin adjuntos. Los soportes que decida cargar conservarán las validaciones de formato y tamaño.</p>
        <div class="form-grid">
            <label>Documentos de soporte<textarea name="supports" <?=(int)$c['supports_required']?'required':''?> rows="3"><?=e($c['supports'])?></textarea></label>
            <label>Condiciones y frecuencia<textarea name="notes" rows="3"><?=e($c['notes'])?></textarea></label>
            <?php if ($c['slug']!=='cumpleanos'): ?>
            <label>Máximo de horas por solicitud<input name="max_hours" type="number" step="0.05" min="0.05" value="<?=$c['max_minutes']!==null?e($c['max_minutes']/60):''?>" <?=$c['slug']==='ordinario'?'disabled':''?>><small><?=$c['slug']==='ordinario'?'El cupo semestral es de 24 horas para trabajador oficial y 25 h 30 min para las demás vinculaciones.':'Vacío: sin límite por horas para cada solicitud.'?></small></label>
            <?php endif; ?>
            <?php if ($annual): ?>
            <input type="hidden" name="annual_year" value="<?=$year?>">
            <label>Días completos por funcionario en <?=$year?><input name="annual_days" type="number" min="1" max="<?=(int)date('z',strtotime("$year-12-31"))+1?>" step="1" value="<?=e($annualLimit??'')?>"><small>Deja el campo vacío para que esta vigencia quede sin límite anual.</small></label>
            <?php endif; ?>
            <label class="check-label"><input type="checkbox" name="policy_confirmed" <?=(int)$c['policy_confirmed']?'checked':''?>> Regla revisada por Talento Humano</label>
        </div>
        <button class="button secondary small">Guardar regla</button>
    </form>
    <?php else: ?>
    <p><strong>Carga de soporte:</strong> <?=(int)$c['supports_required']?'Obligatoria':'Opcional'?>.</p>
    <?php if($c['supports']!==''): ?><p><strong>Documentos de soporte:</strong> <?=e($c['supports'])?></p><?php endif; ?>
    <p><?=e($c['notes'])?></p>
    <?php if($c['max_minutes']!==null && (int)$c['policy_confirmed']): ?><p>Máximo por solicitud: <?=e(hours((int)$c['max_minutes']))?></p><?php endif; ?>
    <?php if($annual): ?><p><strong>Cupo de <?=$year?></strong> · <?=$annualLimit===null?'Sin límite anual':$annualLimit.' día(s) completo(s) por funcionario'?>.</p><?php endif; ?>
    <?php endif; ?>
</section>
<?php endforeach; ?>
