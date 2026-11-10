<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$options=getopt('',['config:']);if(!empty($options['config'])){putenv('APP_TESTING=1');putenv('APP_DATABASE_CONFIG='.$options['config']);}
require __DIR__.'/../app/bootstrap.php';
atomic(function(){
 if(setting('official_worker_calendar_version')==='1'){echo "Actualización de saldos ya aplicada.\n";return;}
 $people=rows("SELECT id FROM user_profiles WHERE appointment_type='Trabajador oficial'");foreach($people as $person)captureBalances((int)$person['id']);
 setSetting('official_worker_calendar_version','1');echo count($people)." saldos actuales actualizados; se conservó el historial.\n";
});
