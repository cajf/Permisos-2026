<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$options=getopt('',['username:','config:']);
if(!empty($options['config'])){putenv('APP_TESTING=1');putenv('APP_DATABASE_CONFIG='.$options['config']);}
require __DIR__.'/../app/bootstrap.php';
$username=(string)($options['username']??'');ensure($username!=='','Indique --username.');
atomic(function()use($username){
 $person=row('SELECT * FROM users WHERE username=? AND active=1',[$username]);ensure($person!==null,'No existe un usuario activo con ese nombre.');
 ensure(hasRole($person,'admin')||isSuperadmin($person),'La asignación inicial requiere una cuenta administradora existente.');
 if(isSuperadmin($person)){echo "El usuario ya es superadministrador.\n";return;}
 ensure(!row("SELECT id FROM users WHERE JSON_CONTAINS(roles,?)",[json_encode('superadmin')]),'La asignación inicial ya se realizó. Use el superadministrador existente.');
 $roles=roles($person);$roles[]='superadmin';run('UPDATE users SET roles=? WHERE id=?',[json_encode($roles),$person['id']]);
 event((int)$person['id'],'Superadministrador inicial asignado','Asignación explícita mediante mantenimiento local a '.$username);
 echo "Superadministrador asignado; contraseña conservada.\n";
});
