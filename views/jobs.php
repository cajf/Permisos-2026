<?php
authorizeAdmin($u);
$jobs=rows('SELECT * FROM jobs ORDER BY title');
$editing=!empty($_GET['id'])?row('SELECT * FROM jobs WHERE id=?',[(int)$_GET['id']]):null;
$data=($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='job')?$_POST:($editing??[]);
?>
<p class="page-intro">Registra cada cargo con un único código y agrega los grados que le corresponden. Estas opciones estarán disponibles al crear, actualizar o importar funcionarios.</p>
<section class="panel"><div class="panel-header"><h2>Catálogo de cargos</h2><a class="text-link" href="/?page=users">Ir a funcionarios →</a></div><div class="table-wrap"><table><thead><tr><th>Cargo</th><th>Código</th><th>Grados disponibles</th><th></th></tr></thead><tbody>
<?php foreach($jobs as $job): $grades=rows('SELECT grade FROM job_grades WHERE job_id=? ORDER BY grade',[$job['id']]); ?><tr><td><?=e($job['title'])?></td><td><?=e($job['code']?:'Sin código registrado')?></td><td><?=e(implode(', ',array_map(fn($g)=>$g['grade']===''?'Sin grado registrado':$g['grade'],$grades)))?></td><td><a class="text-link" href="/?page=jobs&id=<?=(int)$job['id']?>#job-form">Editar cargo</a></td></tr><?php endforeach; ?>
<?php if(!$jobs): ?><tr><td colspan="4">Registra el primer cargo para comenzar.</td></tr><?php endif; ?>
</tbody></table></div></section>
<div class="detail-columns"><section class="panel form-panel" id="job-form"><h2><?=$editing?'Editar cargo':'Nuevo cargo'?></h2><form method="post"><?=csrf()?><input type="hidden" name="action" value="job"><input type="hidden" name="job_id" value="<?=e($data['job_id']??$editing['id']??'')?>">
<label>Nombre del cargo<input name="title" required maxlength="180" value="<?=e($data['title']??'')?>"></label>
<label>Código único del cargo<input name="code" required maxlength="180" value="<?=e($data['code']??'')?>"><small>Se conservan los ceros iniciales.</small></label>
<?php if(!$editing): ?><label>Primer grado<input name="grade" required maxlength="180" value="<?=e($data['grade']??'')?>"></label><?php else: ?><p class="fine">Los cambios se aplican a los datos actuales de los funcionarios asociados. Las solicitudes conservan los datos con los que fueron radicadas.</p><?php endif; ?>
<button class="button">Guardar cargo</button><?php if($editing): ?> <a class="text-link" href="/?page=jobs">Nuevo cargo</a><?php endif; ?></form></section>
<section class="panel form-panel"><h2>Agregar un grado</h2><form method="post"><?=csrf()?><input type="hidden" name="action" value="job_grade"><label>Cargo<select name="job_id" required><option value="">Selecciona un cargo</option><?php foreach($jobs as $job): ?><option value="<?=(int)$job['id']?>" <?=((int)($editing['id']??0)===(int)$job['id'])?'selected':''?>><?=e($job['title'].' · '.($job['code']?:'Sin código'))?></option><?php endforeach; ?></select></label><label>Nuevo grado<input name="grade" required maxlength="180" value="<?=e(($_POST['action']??'')==='job_grade'?($_POST['grade']??''):'')?>"></label><button class="button secondary">Agregar grado</button></form><p class="fine">Los cargos existentes sin código o grado se conservan para no alterar información anterior. Completa su código desde «Editar cargo» y registra los grados correspondientes.</p></section></div>
