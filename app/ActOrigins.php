<?php
declare(strict_types=1);

function actOriginKey(?string $origin):string {
    return in_array($origin,['supplementary','bicycle'],true)?$origin:'unknown';
}
function actRequestOrigin(array $allocations):string {
    $keys=[];
    foreach($allocations as $a) if((int)$a['minutes']>0) $keys[actOriginKey($a['origin']??null)]=true;
    return count($keys)>1?'mixed':(array_key_first($keys)??'unknown');
}
function actRequestOriginLabel(string $key):string {
    return $key==='mixed'?'Mixta':actOriginLabel($key==='unknown'?null:$key);
}
/** Distribuye un tramo por orden de asignación, igual que los reintegros por cancelación. */
function actOriginSeconds(array $allocations,int $offset,int $seconds):array {
    $result=['supplementary'=>0,'bicycle'=>0,'unknown'=>0];$position=0;
    foreach($allocations as $a) {
        $end=$position+(int)$a['minutes']*60;
        $used=max(0,min($end,$offset+$seconds)-max($position,$offset));
        $result[actOriginKey($a['origin']??null)]+=$used;$position=$end;
    }
    // Datos históricos sin asignaciones completas no se atribuyen a un origen conocido.
    $result['unknown']+=max(0,$seconds-array_sum($result));
    return $result;
}
