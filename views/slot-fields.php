<?php foreach($slots as $i=>$s): ?>
<div class="slot-row">
<label>Fecha de la jornada *<input type="date" name="slots[<?=$i?>][date]" min="<?=date('Y-m-d')?>" value="<?=e($s['date'])?>" required></label>
<label>Modalidad<select name="slots[<?=$i?>][mode]" data-mode><option value="hours" <?=($s['mode']??'')==='hours'?'selected':''?>>Por horas</option><option value="full" <?=($s['mode']??'')==='full'?'selected':''?>>Día completo</option></select></label>
<label>Desde<input type="time" data-request-time name="slots[<?=$i?>][start]" value="<?=e($s['start']??'07:30')?>"></label>
<label>Hasta<input type="time" data-request-time name="slots[<?=$i?>][end]" value="<?=e($s['end']??'09:30')?>"></label>
<button type="button" class="icon-button remove-slot" aria-label="Eliminar fecha">×</button>
<small class="slot-result" aria-live="polite"></small>
<label class="check-label special-toggle"><input type="checkbox" name="slots[<?=$i?>][special]" value="1" data-special <?=!empty($s['special'])?'checked':''?>> Registrar horario especial para esta jornada</label>
<fieldset class="special-fields" <?=empty($s['special'])?'hidden':''?>><legend>Jornada especial · trabajo efectivo entre 8 y 8,5 horas</legend>
<p class="fine">Registra únicamente las franjas de trabajo, dejando por fuera los descansos. Día +0 es la fecha seleccionada y día +1 es el día siguiente. Puede ser sábado, domingo o festivo. Máximo tres franjas y 24 horas entre el inicio y el final del turno.</p>
<?php for($j=0;$j<3;$j++): $period=$s['periods'][$j]??[]; ?>
<div class="special-period">
<label>Inicio franja <?=$j+1?><input type="time" name="slots[<?=$i?>][periods][<?=$j?>][start]" value="<?=e($period['start']??'')?>"></label>
<label>Día de inicio<select name="slots[<?=$i?>][periods][<?=$j?>][start_day]"><option value="0">Día +0</option><option value="1" <?=(int)($period['start_day']??0)===1?'selected':''?>>Día +1</option></select></label>
<label>Fin franja <?=$j+1?><input type="time" name="slots[<?=$i?>][periods][<?=$j?>][end]" value="<?=e($period['end']??'')?>"></label>
<label>Día de fin<select name="slots[<?=$i?>][periods][<?=$j?>][end_day]"><option value="0">Día +0</option><option value="1" <?=(int)($period['end_day']??0)===1?'selected':''?>>Día +1</option></select></label>
</div><?php endfor; ?>
<div class="form-grid"><label>Día de inicio del tiempo solicitado<select name="slots[<?=$i?>][start_day]" data-request-day><option value="0">Día +0</option><option value="1" <?=(int)($s['start_day']??0)===1?'selected':''?>>Día +1</option></select></label><label>Día de fin del tiempo solicitado<select name="slots[<?=$i?>][end_day]" data-request-day><option value="0">Día +0</option><option value="1" <?=(int)($s['end_day']??0)===1?'selected':''?>>Día +1</option></select></label></div>
</fieldset></div>
<?php endforeach; ?>
