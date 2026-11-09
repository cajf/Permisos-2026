<?php
declare(strict_types=1);

final class WorkCalendar {
    public function __construct(public readonly bool $officialWorker=false) {}
    public function isBusinessDay(string $date): bool {
        ensure(validDate($date),'La fecha no es válida.');
        return (int)(new DateTimeImmutable($date))->format('N')<=5 && row('SELECT date FROM holidays WHERE date=?',[$date])===null;
    }
    public function periods(string $date): array {
        ensure(validDate($date),'La fecha no es válida.');
        $weekday=(int)(new DateTimeImmutable($date))->format('N');
        if (!$this->isBusinessDay($date)) return [];
        return $this->schedule($date)['periods'][(string)$weekday]??[];
    }
    public static function schoolBreak(int $year): array {
        return json_decode(setting('school_break_'.$year,'{}'),true,512,JSON_THROW_ON_ERROR);
    }
    public function schoolBreakDates(DateTimeImmutable $clock): array {
        $break=self::schoolBreak((int)$clock->format('Y'));
        $days=[];
        if (!$break) return $days;
        $now=$clock->format('Y-m-d H:i:s');
        for ($day=new DateTimeImmutable($break['start']);$day->format('Y-m-d')<=$break['end'];$day=$day->modify('+1 day')) {
            $date=$day->format('Y-m-d');
            $periods=$this->periods($date);
            if ($periods) $days[$date]=$date.' '.$periods[count($periods)-1][1].':00'>$now;
        }
        return $days;
    }
    public function schoolBreakDays(DateTimeImmutable $clock): array {
        return array_keys(array_filter($this->schoolBreakDates($clock)));
    }
    /** Jornada semanal vigente: también puede consultarse en fines de semana y festivos. */
    public function schedule(string $date):array {
        ensure(validDate($date),'La fecha no es válida.');
        if($this->officialWorker){
            foreach(array_reverse(json_decode(setting('official_worker_schedules','[]'),true,512,JSON_THROW_ON_ERROR)) as $schedule)if($schedule['start_date']<=$date&&$schedule['end_date']>=$date)return $schedule+['temporary'=>true];
        }else{
            $override=row('SELECT * FROM schedules WHERE start_date<=? AND end_date>=? ORDER BY id DESC LIMIT 1',[$date,$date]);
            if($override){$override['periods']=json_decode($override['periods'],true,512,JSON_THROW_ON_ERROR);return $override+['temporary'=>true];}
        }
        $periods=[];for($day=1;$day<=5;$day++)$periods[$day]=$this->officialWorker?[['07:00','15:00']]:($day===5?[['07:00','12:00'],['12:30','16:00']]:[['07:30','12:00'],['13:00','17:00']]);
        return ['name'=>'Jornada ordinaria','temporary'=>false,'periods'=>$periods,'start_date'=>null,'end_date'=>null];
    }
    public function minutes(string $date,string $start,string $end): int {
        ensure(preg_match('/^\d{2}:\d{2}$/D',$start)===1 && preg_match('/^\d{2}:\d{2}$/D',$end)===1,'Use horas en formato HH:MM.');
        foreach ([$start,$end] as $t) ensure((int)substr($t,0,2)<24 && (int)substr($t,3,2)<60,'La hora no es válida.');
        ensure($end>$start,'La hora final debe ser posterior a la inicial.');
        $minutes=0;
        foreach ($this->periods($date) as [$a,$b]) {
            $from=max(strtotime("$date $start"),strtotime("$date $a"));
            $to=min(strtotime("$date $end"),strtotime("$date $b"));
            $minutes+=max(0,(int)(($to-$from)/60));
        }
        return $minutes;
    }
    public function between(DateTimeImmutable $from,DateTimeImmutable $to): int {
        if ($to<=$from) return 0;
        ensure($from->diff($to)->days<=1100,'El intervalo supera tres años.');
        $sum=0;
        for ($d=$from->setTime(0,0);$d<=$to;$d=$d->modify('+1 day')) {
            $date=$d->format('Y-m-d');
            foreach ($this->periods($date) as [$a,$b]) {
                $start=max($from->getTimestamp(),strtotime("$date $a"));
                $end=min($to->getTimestamp(),strtotime("$date $b"));
                $sum+=max(0,(int)(($end-$start)/60));
            }
        }
        return $sum;
    }
    /** Anticipación: todas las horas del día hábil, excluyendo días no hábiles completos. */
    public function noticeMinutes(DateTimeImmutable $from,DateTimeImmutable $to): int {
        $zone=new DateTimeZone('America/Bogota');
        $from=$from->setTimezone($zone);$to=$to->setTimezone($zone);
        if ($to<=$from) return 0;
        ensure($from->diff($to)->days<=1100,'El intervalo supera tres años.');
        $seconds=0;
        for ($day=$from->setTime(0,0);$day<$to;$day=$day->modify('+1 day')) {
            if (!$this->isBusinessDay($day->format('Y-m-d'))) continue;
            $seconds+=max(0,min($to->getTimestamp(),$day->modify('+1 day')->getTimestamp())-max($from->getTimestamp(),$day->getTimestamp()));
        }
        return intdiv($seconds,60);
    }
    public function slots(array $input,DateTimeImmutable $clock): array {
        ensure(count($input)>0 && count($input)<=90,'Registre entre una y 90 fechas.');
        $slots=[];
        foreach ($input as $item) {
            ensure(empty($item['special']) && empty($item['periods']) && empty($item['start_day']) && empty($item['end_day']),'Actualice el formulario: solo se admite la jornada laboral configurada.');
            $date=trim($item['date']??'');
            ensure(validDate($date),'Seleccione una fecha válida.');
            ensure($date>=$clock->modify('-2 years')->format('Y-m-d') && $date<=$clock->modify('+2 years')->format('Y-m-d'),'Las fechas deben estar dentro de los dos años anteriores o siguientes a hoy.');
            $periods=$this->periods($date);
            ensure(count($periods)>0,"El $date no es un día laborable.");
            $full=($item['mode']??'hours')==='full';
            $a=$full?$periods[0][0]:($item['start']??'');
            $b=$full?$periods[count($periods)-1][1]:($item['end']??'');
            $minutes=$this->minutes($date,$a,$b);
            ensure($minutes>0,'El horario debe incluir tiempo laborable.');
            ensure($a>=$periods[0][0] && $b<=$periods[count($periods)-1][1],'El horario solicitado está fuera de la jornada.');
            foreach ($slots as $old) ensure(!($old['date']===$date && $a<$old['end_time'] && $b>$old['start_time']),'Las franjas de una solicitud no pueden superponerse.');
            $segments=[]; foreach ($periods as [$x,$y]) if (max($a,$x)<min($b,$y)) $segments[]=[max($a,$x),min($b,$y)];
            $slots[]=['date'=>$date,'start_time'=>$a,'end_time'=>$b,'minutes'=>$minutes,'segments'=>json_encode($segments),'semester'=>substr($date,0,4).'-'.((int)substr($date,5,2)<=6?'1':'2'),'start_at'=>$date.' '.$a.':00','end_at'=>$date.' '.$b.':00','is_full_day'=>$full?1:0];
        }
        usort($slots,fn($a,$b)=>strcmp($a['date'].$a['start_time'],$b['date'].$b['start_time']));
        ensure(max(array_column($slots,'end_at'))>$clock->format('Y-m-d H:i:s'),'El periodo de disfrute ya terminó. No se permite registrar periodos completamente terminados.');
        return $slots;
    }
}
function isOfficialWorker(array $person):bool {return ($person['appointment_type']??'')==='Trabajador oficial';}
function ordinaryQuotaLimit(array $person):int {return isOfficialWorker($person)?1440:1530;}
