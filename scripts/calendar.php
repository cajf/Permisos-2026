<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';require ROOT.'/database/holidays.php';
$year=isset($argv[1])?(int)$argv[1]:(int)date('Y');ensure($year>=2000&&$year<=2100,'Año fuera de rango.');
for($y=$year;$y<=$year+2;$y++)foreach(nationalHolidays($y)as $date=>$name)run('INSERT INTO holidays(date,name)VALUES(?,?) ON DUPLICATE KEY UPDATE name=name',[$date,$name]);
echo "Festivos precargados para ".$year.' a '.($year+2).". Se conservaron los registros existentes.\n";
