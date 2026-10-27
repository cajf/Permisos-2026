<?php
declare(strict_types=1);
// Calendario nacional; fuentes oficiales y alcance documentados en docs/manual.html.
function nationalHolidays(int $year): array {
    $a=$year%19; $b=intdiv($year,100); $c=$year%100; $d=intdiv($b,4); $e=$b%4;
    $f=intdiv($b+8,25); $g=intdiv($b-$f+1,3); $h=(19*$a+$b-$d-$g+15)%30;
    $i=intdiv($c,4); $k=$c%4; $l=(32+2*$e+2*$i-$h-$k)%7; $m=intdiv($a+11*$h+22*$l,451);
    $month=intdiv($h+$l-7*$m+114,31); $day=($h+$l-7*$m+114)%31+1;
    $easter=new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$day));
    $out=[]; $add=function(DateTimeImmutable $date,string $name,bool $monday=false)use(&$out){ if($monday&&(int)$date->format('N')!==1)$date=$date->modify('next monday');$key=$date->format('Y-m-d');$out[$key]=isset($out[$key])?$out[$key].' / '.$name:$name; };
    foreach(['01-01'=>'Año Nuevo','05-01'=>'Día del Trabajo','07-20'=>'Independencia de Colombia','08-07'=>'Batalla de Boyacá','12-08'=>'Inmaculada Concepción','12-25'=>'Navidad'] as $date=>$name) $add(new DateTimeImmutable("$year-$date"),$name);
    foreach(['01-06'=>'Reyes Magos','03-19'=>'San José','06-29'=>'San Pedro y San Pablo','08-15'=>'Asunción','10-12'=>'12 de octubre','11-01'=>'Todos los Santos','11-11'=>'Independencia de Cartagena'] as $date=>$name) $add(new DateTimeImmutable("$year-$date"),$name,true);
    $add($easter->modify('-3 days'),'Jueves Santo'); $add($easter->modify('-2 days'),'Viernes Santo');
    foreach([39=>'Ascensión del Señor',60=>'Corpus Christi',68=>'Sagrado Corazón'] as $offset=>$name) $add($easter->modify("+$offset days"),$name,true);
    if($year>=2026)$add(new DateTimeImmutable("$year-07-09"),'Nuestra Señora del Rosario de Chiquinquirá',true);
    ksort($out);return $out;
}
