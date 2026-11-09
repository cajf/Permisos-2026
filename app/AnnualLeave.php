<?php
declare(strict_types=1);

final class AnnualLeave {
    public const TYPES=['bienestar','receso','quinquenio','cumpleanos'];

    public static function limit(string $type,int $year): ?int {
        $value=setting("annual_days_{$type}_{$year}",$type==='cumpleanos'?'1':'');
        return $value===''?null:(int)$value;
    }

    public static function saveLimit(string $type,int $year,mixed $input): string {
        ensure(in_array($type,self::TYPES,true) && is_string($input),'Indique un cupo anual válido.');
        $value=trim($input);
        $daysInYear=(int)(new DateTimeImmutable("$year-12-31"))->format('z')+1;
        ensure($value==='' || filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>$daysInYear]])!==false,'Ingrese un número entero de días entre 1 y '.$daysInYear.' o deje el campo vacío.');
        setSetting("annual_days_{$type}_{$year}",$value);
        return $value===''?'Sin límite anual':"$value día(s) por vigencia";
    }

    public static function remaining(Workflow $workflow,int $userId,array $category,int $year,?int $exclude=null): ?int {
        $limit=self::limit($category['slug'],$year);
        if ($limit===null) return null;
        $parameters=[$userId,$category['id'],$exclude??0];
        $period='s.date BETWEEN ? AND ?';
        array_push($parameters,"$year-01-01","$year-12-31");
        if ($category['slug']==='cumpleanos') {
            $period="(r.birthday_year=? OR (r.birthday_year IS NULL AND $period))";
            array_splice($parameters,3,0,[$year]);
        }
        $dates=[];
        foreach (rows("SELECT s.*,r.status,r.cancelled_at FROM request_slots s JOIN requests r ON r.id=s.request_id WHERE r.user_id=? AND r.category_id=? AND r.id!=? AND r.status!='rejected' AND $period",$parameters) as $slot) {
            if ($slot['status']!=='cancelled' || $workflow->consumedSlot($slot,$slot['cancelled_at'])>0) $dates[$slot['request_id'].'/'.$slot['date']]=true;
        }
        return max(0,$limit-count($dates));
    }
}
