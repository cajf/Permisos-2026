<?php
requirePermission($u,'view.accounts');
$selectedId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$editing=$selectedId?row('SELECT p.id,p.name,p.identification,a.username,a.active,COALESCE(a.roles,JSON_ARRAY()) AS roles
    FROM persons p JOIN person_employments e ON e.person_id=p.id LEFT JOIN user_profiles a ON a.id=p.id WHERE p.id=?',[$selectedId]):null;
$listing=searchAccountDirectory($u,$_GET);
$listingPage='accounts';
$listingFields=[
    'q_name'=>['label'=>'Funcionario'],
    'q_username'=>['label'=>'Usuario'],
    'q_identification'=>['label'=>'Cédula'],
    'q_role'=>['label'=>'Rol','options'=>['employee'=>'Solicitante']+systemRoles()],
];
?>
<section class="panel">
    <div class="panel-header"><h2>Directorio de usuarios</h2>
        <?php if(can($u,'users.edit')): ?><a class="button secondary" href="#new-account">Nuevo usuario</a><?php endif; ?>
    </div>
    <?php require ROOT.'/views/listing-filters.php'; ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Usuario</th><th>Funcionario</th><th>Roles</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach($listing['items'] as $entry): ?>
            <tr>
                <td><?=e($entry['username'])?></td>
                <td><?=e($entry['name'])?><small><?=e($entry['identification'])?></small></td>
                <td><?=e(implode(', ',array_map('roleLabel',roles($entry)))?:'Solicitante')?></td>
                <td><?=(int)$entry['active']?'Activa':'Desactivada'?></td>
                <td><?php if(can($u,'users.edit') || can($u,'users.reset')): ?><a class="text-link" href="/?page=accounts&amp;id=<?=(int)$entry['id']?>#<?=can($u,'users.edit')?'account-form':'reset-account'?>">Gestionar cuenta</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if(!$listing['items']): ?><tr><td colspan="5">No hay usuarios que coincidan con la consulta.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php require ROOT.'/views/listing-pagination.php'; ?>
</section>

<?php if(can($u,'users.edit')):
    $available=rows('SELECT p.id,p.name,p.identification FROM persons p JOIN person_employments e ON e.person_id=p.id
        LEFT JOIN users u ON u.id=p.id WHERE u.id IS NULL ORDER BY p.name,p.id');
?>
<section class="panel form-panel" id="new-account">
    <h2>Nuevo usuario</h2>
    <?php if($available): ?>
        <form method="get" action="/#account-form" class="filter-form">
            <input type="hidden" name="page" value="accounts">
            <label>Funcionario sin cuenta<select name="id" required>
                <option value="">Selecciona un funcionario</option>
                <?php foreach($available as $person): ?>
                    <option value="<?=(int)$person['id']?>" <?=($editing['id']??null)===$person['id']?'selected':''?>><?=e($person['name'].' · '.$person['identification'])?></option>
                <?php endforeach; ?>
            </select></label>
            <button class="button secondary">Crear cuenta</button>
        </form>
    <?php else: ?><p>No hay funcionarios sin cuenta de acceso.</p><?php endif; ?>
</section>
<?php endif; ?>
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
    <form method="post" action="/?page=accounts&amp;id=<?=(int)$editing['id']?>#account-form">
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
<?php if($editing && $editing['username']!==null && can($u,'users.reset')): ?><section class="panel form-panel" id="reset-account"><h2>Restablecer acceso</h2><form method="post" action="/?page=accounts&amp;id=<?=(int)$editing['id']?>#reset-account"><?=csrf()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?=(int)$editing['id']?>"><label>Nueva contraseña temporal<input name="initial_password" type="password" required minlength="8" maxlength="15" autocomplete="new-password"></label><button class="button secondary">Restablecer contraseña</button></form></section><?php endif; ?>
