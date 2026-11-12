<?php requirePermission($u,'view.users'); $editing=!empty($_GET['id'])?row('SELECT * FROM ('.staffDirectorySource().') staff WHERE id=?',[(int)$_GET['id']]):null; $data=$_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='user'?$_POST:($editing??[]); $listing=searchUserDirectory($u,$_GET);$people=$listing['items']; ?>
<p class="page-intro">Registra los datos personales y laborales del funcionario. Después puedes crear o gestionar su cuenta de acceso. Asigna los responsables de las dependencias desde Designaciones; cada trámite conserva a sus aprobadores originales.</p><section class="panel"><div class="panel-header"><h2>Directorio de funcionarios</h2><a class="text-link" href="/?page=import">Importar desde Excel →</a></div>
<?php $listingPage='users';$jobOptions=[];foreach(rows("SELECT title FROM jobs UNION SELECT job_title title FROM user_profiles WHERE job_title!='' ORDER BY title") as $choice)$jobOptions[$choice['title']]=$choice['title'];
$listingFields=['q_name'=>['label'=>'Nombres o apellidos'],'q_username'=>['label'=>'Usuario'],'q_identification'=>['label'=>'Cédula'],'q_job'=>['label'=>'Cargo','options'=>$jobOptions],'q_role'=>['label'=>'Rol','options'=>listingRoles()]]; require ROOT.'/views/listing-filters.php';require ROOT.'/views/listing-pagination.php'; ?>
<div class="table-wrap"><table><thead><tr><th>Funcionario</th><th>Cargo / dependencia</th><th>Roles</th><th>Estado</th><th></th></tr></thead><tbody><?php foreach($people as $p): ?><tr><td><strong><?=e($p['name'])?></strong><small><?=e($p['identification'])?><?php if($p['username']!==null): ?> · <?=e($p['username'])?><?php endif; ?></small></td><td><?=e($p['job_title'])?><?php if(isOfficialWorker($p)): ?><small>Categoría: <?=e($p['job_category']?:'Sin asignar')?></small><?php endif; ?><small><?=e($p['department'])?></small></td><td><?=$p['username']===null?'—':e(implode(', ',array_map('roleLabel',roles($p)))?:'Solicitante')?></td><td><?=$p['username']===null?'Sin cuenta':((int)$p['active']?'Cuenta activa':'Cuenta desactivada')?></td><td><a class="text-link" href="/?page=users&id=<?=(int)$p['id']?>#user-form">Editar funcionario</a><?php if(can($u,'users.edit')): ?><br><a class="text-link" href="/?page=users&amp;id=<?=(int)$p['id']?>#account-form"><?=$p['username']===null?'Crear cuenta':'Gestionar cuenta'?></a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$people): ?><tr><td colspan="5">No hay funcionarios que coincidan con la consulta.</td></tr><?php endif; ?></tbody></table></div></section>
<div class="detail-columns"><section class="panel form-panel" id="user-form"><h2><?=$editing?'Editar funcionario':'Nuevo funcionario'?></h2><form method="post"><?=csrf()?><input type="hidden" name="action" value="user"><input type="hidden" name="id" value="<?=e($editing['id']??'')?>"><div class="form-grid"><?php foreach(['name'=>'Nombre completo','identification'=>'Cédula','email'=>'Correo electrónico'] as $key=>$label): ?><label><?=e($label)?><input name="<?=$key?>" value="<?=e($data[$key]??'')?>" <?=in_array($key,['code','grade'])?'':'required'?> <?=$key==='email'?'type="email"':''?> maxlength="180"></label><?php endforeach; ?><?php $catalog=jobCatalog(); $selectedTitle=trim($data['job_title']??''); $selectedGrade=trim($data['grade']??''); $catalogJobs=[]; foreach($catalog as $entry)$catalogJobs[$entry['job_id']]=$entry; ?>
<label>Cargo<select name="job_title" id="user-job" required><option value="">Selecciona un cargo</option><?php foreach($catalogJobs as $entry): ?><option value="<?=e($entry['title'])?>" data-group="<?=e($entry['employment_group'])?>" data-category="<?=e($entry['job_category']??'')?>" data-code="<?=e($entry['code'])?>" <?=$selectedTitle===$entry['title']?'selected':''?>><?=e($entry['title'])?></option><?php endforeach; ?></select><small><a href="/?page=jobs">Administrar cargos, códigos y grados</a></small></label>
<label>Categoría de trabajador oficial<input id="user-job-category" readonly value="<?=e($data['job_category']??'')?>"><small>Se carga según el cargo.</small></label><label>Código<input name="code" id="user-job-code" readonly value="<?=e($data['code']??'')?>"><small>Se carga automáticamente según el cargo.</small></label>
<label>Grado<select name="grade" id="user-job-grade" data-legacy-job="<?=e($editing['job_title']??'')?>" data-legacy-blank="<?=($editing && $editing['grade']==='')?'1':'0'?>" <?=($editing && $editing['grade']==='')?'':'required'?>><option value="">Selecciona un grado</option><?php foreach($catalog as $entry): ?><option value="<?=e($entry['grade'])?>" data-job="<?=e($entry['title'])?>" <?=($entry['title']===$selectedTitle && $entry['grade']===$selectedGrade)?'selected':''?>><?=e($entry['grade']===''?'Sin grado registrado':$entry['grade'])?></option><?php endforeach; ?></select></label>
<label>Vinculación<select name="appointment_type" id="user-appointment"><?php foreach(['Carrera administrativa','Libre nombramiento y remoción','Provisional','Trabajador oficial'] as $v): ?><option <?=($data['appointment_type']??'')===$v?'selected':''?>><?=e($v)?></option><?php endforeach; ?></select></label><label>Dependencia<select name="department_id" required><?php foreach(rows('SELECT * FROM departments ORDER BY name') as $d): ?><option value="<?=$d['id']?>" <?=(int)($data['department_id']??0)===(int)$d['id']?'selected':''?>><?=e($d['name'])?></option><?php endforeach; ?></select></label><label>Fecha de nacimiento<input type="date" name="birth_date" max="<?=date('Y-m-d',strtotime('-1 day'))?>" value="<?=e($data['birth_date']??'')?>"><small>Requerida para validar el beneficio de Cumpleaños.</small></label><label>Clasificación de alto rango<select name="high_rank"><?php foreach([''=>'No aplica','Asesor de Despacho'=>'Asesor de Despacho','Secretario de Despacho'=>'Secretario de Despacho','Jefe de Oficina'=>'Jefe de Oficina'] as $v=>$l): ?><option value="<?=e($v)?>" <?=($data['high_rank']??'')===$v?'selected':''?>><?=e($l)?></option><?php endforeach; ?></select></label></div><button class="button">Guardar funcionario</button></form></section>
<aside><section class="panel form-panel"><h2>Nueva dependencia</h2><form method="post"><?=csrf()?><input type="hidden" name="action" value="department"><label>Nombre<input name="name" required maxlength="180"></label><button class="button secondary">Crear dependencia</button></form></section><?php if($editing && $editing['username']!==null): ?><section class="panel form-panel"><h2>Restablecer acceso</h2><form method="post"><?=csrf()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?=(int)$editing['id']?>"><label>Nueva contraseña temporal<input name="initial_password" type="password" required minlength="8" maxlength="15" autocomplete="new-password"></label><button class="button secondary">Restablecer contraseña</button></form></section><?php endif; ?></aside></div>

<?php if($editing && can($u,'users.edit')):
    $account=$editing;
    $accountRoles=roles($editing);
    if(($_POST['action']??'')==='account_save') {
        $account=array_replace($account,array_intersect_key($_POST,array_flip(['username','active'])));
        if(can($u,'users.roles') && is_array($_POST['roles']??[])) $accountRoles=$_POST['roles']??[];
    }
    $hasAccount=$editing['username']!==null;
?>
<section class="panel form-panel" id="account-form">
    <h2><?=$hasAccount?'Gestionar cuenta de acceso':'Crear cuenta de acceso'?></h2>
    <p><?=e($editing['name'])?> · <?=e($editing['identification'])?></p>
    <form method="post" action="/?page=users&amp;id=<?=(int)$editing['id']?>#account-form">
        <?=csrf()?>
        <input type="hidden" name="action" value="account_save">
        <input type="hidden" name="id" value="<?=(int)$editing['id']?>">
        <div class="form-grid">
            <label>Usuario<input name="username" required minlength="3" maxlength="80" autocomplete="off" value="<?=e($account['username']??'')?>"></label>
            <label>Estado de la cuenta<select name="active">
                <option value="1" <?=(int)($account['active']??1)===1?'selected':''?>>Activa</option>
                <option value="0" <?=(int)($account['active']??1)===0?'selected':''?>>Desactivada</option>
            </select></label>
            <?php if(!$hasAccount): ?>
                <label>Contraseña inicial<input name="initial_password" type="password" required minlength="8" maxlength="15" autocomplete="new-password">
                    <small>El funcionario deberá cambiarla al ingresar.</small>
                </label>
            <?php endif; ?>
        </div>
        <?php if(can($u,'users.roles')): ?>
            <fieldset><legend>Roles adicionales al de solicitante</legend><div class="checks">
                <?php foreach(systemRoles() as $code=>$label):
                    if($code==='employee' || (in_array($code,['superadmin','labor_records'],true) && !isSuperadmin($u))) continue;
                ?>
                    <label><input type="checkbox" name="roles[]" value="<?=e($code)?>" <?=in_array($code,$accountRoles,true)?'checked':''?>> <?=e($label)?></label>
                <?php endforeach; ?>
            </div></fieldset>
        <?php endif; ?>
        <button class="button">Guardar cuenta</button>
    </form>
</section>
<?php endif; ?>