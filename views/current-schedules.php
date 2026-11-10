<?php $scheduleToday=date('Y-m-d');foreach([false=>'Otras vinculaciones',true=>'Trabajador oficial'] as $worker=>$label):$calendarForGroup=new WorkCalendar((bool)$worker);$activeSchedule=$calendarForGroup->schedule($scheduleToday); ?>
<section class="panel"><div class="panel-header"><div><h2><?=e($label)?></h2><p>Jornada vigente al <?=e($scheduleToday)?> · hora de Bogotá</p></div><span class="badge"><?=$activeSchedule['temporary']?'Horario temporal activo':'Jornada ordinaria activa'?></span></div>
<p><strong><?=e($activeSchedule['name'])?></strong><?php if($activeSchedule['temporary']): ?><br>Vigencia: <?=e($activeSchedule['start_date'].' a '.$activeSchedule['end_date'])?><?php endif; ?></p>
<div class="table-wrap"><table><thead><tr><th>Día</th><th>Franjas de trabajo</th><th>Total diario</th></tr></thead><tbody>
<?php foreach([1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes'] as $day=>$dayLabel):$ranges=$activeSchedule['periods'][$day]??[];$dailyMinutes=0;foreach($ranges as [$from,$to])$dailyMinutes+=(int)substr($to,0,2)*60+(int)substr($to,3,2)-(int)substr($from,0,2)*60-(int)substr($from,3,2); ?>
<tr><td><?=e($dayLabel)?></td><td><?=e($ranges?implode(' · ',array_map(fn($p)=>$p[0].' – '.$p[1],$ranges)):'Sin jornada')?></td><td><?=e(hours($dailyMinutes))?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if($activeSchedule['temporary']): ?><p class="fine">Al finalizar esta vigencia se aplica nuevamente la jornada ordinaria: <?=$worker?'lunes a viernes, 07:00–15:00 (8 horas continuas).':'lunes a jueves, 07:30–12:00 y 13:00–17:00; viernes, 07:00–12:00 y 12:30–16:00 (8 h 30 min).'?></p><?php endif; ?>
<p class="fine">Sábados, domingos y festivos no son laborables.</p></section>
<?php endforeach; ?>
