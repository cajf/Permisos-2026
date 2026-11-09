<?php foreach($slots as $i=>$s): ?>
<div class="slot-row">
<label>Fecha de la jornada *
    <input type="date" name="slots[<?=$i?>][date]" min="<?=date('Y-m-d',strtotime('-2 years'))?>" value="<?=e($s['date'])?>" <?=$isRecess?'hidden disabled':'required'?>>
    <select aria-label="Fecha de receso escolar" data-recess-date name="slots[<?=$i?>][date]" <?=$isRecess?'required':'hidden disabled'?>>
        <option value="">Selecciona un día hábil</option>
        <?php foreach($recessDays as $date): ?><option value="<?=e($date)?>" <?=$s['date']===$date?'selected':''?>><?=e(date('d/m/Y',strtotime($date)))?></option><?php endforeach; ?>
    </select>
</label>
<label>Modalidad<select name="slots[<?=$i?>][mode]" data-mode><option value="hours" <?=($s['mode']??'')==='hours'?'selected':''?>>Por horas</option><option value="full" <?=($selectedCategory['slug']??'')==='acto'?'disabled':''?> <?=($s['mode']??'')==='full'?'selected':''?>>Día completo</option></select></label>
<label>Desde<input type="time" data-request-time name="slots[<?=$i?>][start]" value="<?=e($s['start']??(isOfficialWorker($u)?'07:00':'07:30'))?>"></label>
<label>Hasta<input type="time" data-request-time name="slots[<?=$i?>][end]" value="<?=e($s['end']??'09:30')?>"></label>
<button type="button" class="icon-button remove-slot" aria-label="Eliminar fecha">×</button>
<small class="slot-result" aria-live="polite"></small>
</div>
<?php endforeach; ?>
