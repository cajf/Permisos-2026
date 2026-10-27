<?php
declare(strict_types=1);

final class Workflow {
    public WorkCalendar $calendar;
    public function __construct() { $this->calendar=new WorkCalendar(); }
    public function approver(string $role,array $owner,?string $date=null): int {
        $designation=in_array($role,['manager','subsecretary'],true)?designation($role,$role==='manager'?(int)$owner['department_id']:null,$date):null;
        $id=$designation?(int)$designation['user_id']:($role==='manager'?(int)$owner['manager_id']:(int)setting($role.'_id'));
        if ($id===(int)$owner['id']) $id=$designation?(int)$designation['alternate_id']:($role==='manager'?(int)$owner['deputy_manager_id']:(int)setting($role.'_alternate_id'));
        $u=row('SELECT * FROM users WHERE id=? AND active=1',[$id]);
        ensure($u!==null && hasRole($u,$role) && $id!==(int)$owner['id'],'Falta un aprobador activo o suplente válido para el rol '.['manager'=>'jefe inmediato','subsecretary'=>'subsecretario','mayor'=>'alcalde'][$role].'. Talento Humano debe configurar la asignación.');
        return $id;
    }
    public function route(array $owner,array $category,int $minutes,?string $date=null): array {
        $roles=$owner['high_rank']!=='' && $category['family']==='permiso' ? ['mayor'] : ($category['slug']==='ordinario' && $minutes<=120?['manager']:['manager','subsecretary']);
        $route=[]; foreach ($roles as $role) {
            $designation=in_array($role,['manager','subsecretary'],true)?designation($role,$role==='manager'?(int)$owner['department_id']:null,$date):null;
            $route[]=['role'=>$role,'assigned_user_id'=>$this->approver($role,$owner,$date),'designation_id'=>$designation['id']??null];
        }
        return $route;
    }
    public function available(array $act): int {
        $used=(int)(row("SELECT COALESCE(SUM(minutes-returned_minutes),0) n FROM allocations WHERE act_id=? AND state IN ('reserved','consumed')",[$act['id']])['n']);
        return (int)$act['granted_minutes']-$used;
    }
    public function consumedSlot(array $slot,?string $until): int {
        if (!$until) return (int)$slot['minutes'];
        $sum=0;
        foreach (json_decode($slot['segments'],true,512,JSON_THROW_ON_ERROR) as [$a,$b]) $sum+=max(0,(int)((min(strtotime($until),strtotime(strlen($b)>5?$b:$slot['date'].' '.$b))-strtotime(strlen($a)>5?$a:$slot['date'].' '.$a))/60));
        return min((int)$slot['minutes'],$sum);
    }
    public function quota(int $user,string $semester,?int $exclude=null): int {
        $sum=0;
        foreach (rows("SELECT s.*,r.status,r.cancelled_at FROM request_slots s JOIN requests r ON r.id=s.request_id JOIN categories c ON c.id=r.category_id WHERE r.user_id=? AND c.slug='ordinario' AND r.status!='rejected' AND r.id!=?",[$user,$exclude??0]) as $s) $sum+=$this->slotSemesters($s,$s['status']==='cancelled'?$s['cancelled_at']:null)[$semester]??0;
        return $sum;
    }
    public function slotSemesters(array $slot,?string $until=null): array {
        $result=[];
        foreach (json_decode($slot['segments'],true,512,JSON_THROW_ON_ERROR) as [$a,$b]) {
            $from=new DateTimeImmutable(strlen($a)>5?$a:$slot['date'].' '.$a);$to=new DateTimeImmutable(strlen($b)>5?$b:$slot['date'].' '.$b);
            if ($until) $to=min($to,new DateTimeImmutable($until));
            for ($at=$from;$at<$to;$at=$next) {
                $next=min($to,$at->modify('tomorrow')->setTime(0,0));
                $semester=$at->format('Y').'-'.((int)$at->format('m')<=6?'1':'2');
                $result[$semester]=($result[$semester]??0)+intdiv($next->getTimestamp()-$at->getTimestamp(),60);
            }
        }
        return $result;
    }
    public function submit(array $owner,array $input,array $files=[],?int $id=null,?DateTimeImmutable $clock=null): int {
        $clock??=new DateTimeImmutable();
        return atomic(function() use($owner,$input,$files,$id,$clock) {
            $old=$id?row('SELECT * FROM requests WHERE id=?',[$id]):null;
            if ($id) ensure($old && (int)$old['user_id']===(int)$owner['id'] && $old['status']==='returned','Solo puede corregir una solicitud propia devuelta.');
            $category=row('SELECT * FROM categories WHERE id=?',[(int)($input['category_id']??0)]);
            ensure($category!==null,'Seleccione un tipo de solicitud.');
            $slots=$this->calendar->slots($input['slots']??[],$clock);
            $total=array_sum(array_column($slots,'minutes'));
            $reason=trim($input['justification']??''); $exception=trim($input['exception_reason']??'');
            ensure(mb_strlen($reason)>=10 && mb_strlen($reason)<=5000,'La justificación debe contener entre 10 y 5.000 caracteres.');
            ensure(mb_strlen($exception)<=3000,'La justificación de excepción es demasiado extensa.');
            $first=$slots[0];
            if ($this->calendar->noticeMinutes($clock,new DateTimeImmutable($first['start_at']))<2880) ensure(mb_strlen($exception)>=10,'No se completan 48 horas calendario dentro de días hábiles. Debe justificar la excepción.');
            if ($category['slug']!=='cumpleanos' && $category['max_minutes']!==null && (int)$category['policy_confirmed']===1) ensure($total<=(int)$category['max_minutes'],'La duración supera el límite configurado para este tipo.');
            $relationship=trim($input['relationship']??'');
            if ($category['slug']==='luto') ensure($relationship!=='','Indique el parentesco para la licencia por luto.');
            $existingFiles=$id?(int)row('SELECT COUNT(*) n FROM attachments WHERE request_id=?',[$id])['n']:0;
            if ((int)$category['supports_required']===1) ensure(count($files)+$existingFiles>=1,'Adjunte al menos un soporte.');
            ensure(count($files)+$existingFiles<=20,'Una solicitud admite hasta veinte soportes.');
            $semesters=[];
            foreach ($slots as $s) {
                foreach ($this->slotSemesters($s) as $semester=>$minutes) $semesters[$semester]=($semesters[$semester]??0)+$minutes;
                $overlap=row("SELECT r.id FROM request_slots s JOIN requests r ON r.id=s.request_id WHERE r.user_id=? AND r.id!=? AND r.status NOT IN ('rejected','cancelled') AND COALESCE(s.start_at,CONCAT(s.date,' ',s.start_time,':00'))<? AND COALESCE(s.end_at,CONCAT(s.date,' ',s.end_time,':00'))>? LIMIT 1",[$owner['id'],$id??0,$s['end_at'],$s['start_at']]);
                ensure(!$overlap,'El horario se cruza con otra solicitud activa.');
            }
            if ($category['slug']==='ordinario') foreach ($semesters as $semester=>$minutes) {
                ensure(substr($semester,0,4)===$clock->format('Y'),'El Ordinario-Remunerado debe disfrutarse en el año en curso.');
                ensure($this->quota((int)$owner['id'],$semester,$id)+$minutes<=1530,'La solicitud excede el cupo de 25 h 30 min del semestre '.$semester.'.');
            }
            $birthdayYear=null;
            if ($category['slug']==='cumpleanos') {
                $birth=$owner['birth_date']??'';
                ensure(validDate($birth) && $birth<$clock->format('Y-m-d'),'Talento Humano debe registrar una fecha de nacimiento válida antes de solicitar Cumpleaños.');
                ensure(count($slots)===1 && (int)$slots[0]['is_full_day']===1,'Cumpleaños se disfruta en una única jornada completa; seleccione Día completo.');
                $birthdayYear=filter_var($input['birthday_year']??$clock->format('Y'),FILTER_VALIDATE_INT);
                ensure($birthdayYear!==false && $birthdayYear>=(int)$clock->format('Y')-1 && $birthdayYear<=(int)$clock->format('Y'),'Seleccione una vigencia de cumpleaños válida.');
                $date=$slots[0]['date'];$normal=substr($date,0,4)===(string)$birthdayYear;
                $carry=substr($birth,5,2)==='12' && $date>=($birthdayYear+1).'-01-01' && $date<=($birthdayYear+1).'-01-30';
                ensure($normal || $carry,'El día de Cumpleaños debe estar en su vigencia; únicamente los nacidos en diciembre pueden disfrutarlo hasta el 30 de enero siguiente.');
                foreach (rows("SELECT r.*,s.* FROM requests r JOIN request_slots s ON s.request_id=r.id JOIN categories c ON c.id=r.category_id WHERE r.user_id=? AND c.slug='cumpleanos' AND COALESCE(r.birthday_year,CAST(substr(s.date,1,4) AS SIGNED))=CAST(? AS SIGNED) AND r.id!=? AND r.status!='rejected'",[$owner['id'],$birthdayYear,$id??0]) as $existing) ensure($existing['status']==='cancelled' && $this->consumedSlot($existing,$existing['cancelled_at'])===0,'El beneficio de cumpleaños de esa vigencia ya está solicitado o disfrutado.');
            }
            $route=$this->route($owner,$category,$total,$clock->format('Y-m-d'));
            $revision=$old?(int)$old['revision']+1:1;
            $snapshotData=array_intersect_key($owner,array_flip(['name','identification','job_title','code','grade','department','appointment_type','high_rank']));
            if ($old) {
                $previousSnapshot=json_decode($old['snapshot'],true,512,JSON_THROW_ON_ERROR);
                if (isset($previousSnapshot['archived_data'])) $snapshotData['archived_data']=$previousSnapshot['archived_data'];
            }
            $snapshot=json_encode($snapshotData,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $data=['user_id'=>$owner['id'],'category_id'=>$category['id'],'status'=>'pending','justification'=>$reason,'exception_reason'=>$exception,'relationship'=>$relationship,'total_minutes'=>$total,'snapshot'=>$snapshot,'revision'=>$revision,'updated_at'=>now(),'route_note'=>implode(' → ',array_column($route,'role')),'department_id'=>$owner['department_id'],'birthday_year'=>$birthdayYear];
            if (!$old) $id=insert('requests',$data+['created_at'=>now()]);
            else {
                run('UPDATE requests SET category_id=?,status=?,justification=?,exception_reason=?,relationship=?,total_minutes=?,snapshot=?,revision=?,updated_at=?,route_note=? WHERE id=?',[$data['category_id'],'pending',$reason,$exception,$relationship,$total,$snapshot,$revision,now(),$data['route_note'],$id]);
                run('UPDATE requests SET department_id=?,birthday_year=? WHERE id=?',[$owner['department_id'],$birthdayYear,$id]);
                run('DELETE FROM request_slots WHERE request_id=?',[$id]);
                run("UPDATE approvals SET status='skipped' WHERE request_id=? AND status='waiting'",[$id]);
                run('DELETE FROM allocations WHERE request_id=?',[$id]);
            }
            foreach ($slots as $s) insert('request_slots',$s+['request_id'=>$id]);
            if ($category['slug']==='acto') {
                $allocated=0; $seen=[];
                foreach ($input['allocations']??[] as $a) {
                    if (empty($a['id']) || (float)($a['hours']??0)<=0) continue;
                    $amount=self::hourInput((string)$a['hours']);
                    $act=row("SELECT * FROM acts WHERE id=? AND user_id=? AND status='approved'",[(int)$a['id'],$owner['id']]);
                    ensure($act!==null,'Seleccione un acto aprobado de su propiedad.');
                    ensure(!in_array($act['id'],$seen,true),'No repita un acto en la misma solicitud.'); $seen[]=$act['id'];
                    $lastEnjoyed=max(array_map(fn($s)=>date('Y-m-d',strtotime($s['end_at'])-1),$slots));
                    ensure($act['issued_on']<=substr($first['start_at'],0,10) && $act['expires_on']>=$lastEnjoyed && $act['expires_on']>=$clock->format('Y-m-d'),'El acto no está vigente para todas las fechas solicitadas.');
                    ensure($this->available($act)>=$amount,'El acto '.$act['number'].' no tiene saldo suficiente.');
                    insert('allocations',['request_id'=>$id,'act_id'=>$act['id'],'minutes'=>$amount]); $allocated+=$amount;
                }
                ensure($allocated===$total,'La suma de horas asignadas a los actos debe coincidir con el tiempo solicitado: '.hours($total).'.');
            }
            foreach ($route as $i=>$step) insert('approvals',$step+['request_id'=>$id,'revision'=>$revision,'step'=>$i+1]);
            saveUploads($files,(int)$owner['id'],$id);
            event((int)$owner['id'],$old?'Solicitud corregida':'Solicitud registrada','Revisión '.$revision.' · '.hours($total),$id);
            notify((int)$route[0]['assigned_user_id'],'Solicitud pendiente TH-'.$id,'Tiene una solicitud pendiente de revisión. Ingrese a la aplicación para consultar los soportes.');
            notify((int)$owner['id'],'Solicitud registrada TH-'.$id,'Su solicitud fue registrada y enviada a aprobación.');
            captureBalances((int)$owner['id']);
            return $id;
        });
    }
    public static function hourInput(string $hours): int {
        ensure(is_numeric($hours) && (float)$hours>0 && (float)$hours<=100000,'Ingrese un total de horas positivo y válido.');
        $raw=(float)$hours*60; ensure(round($raw)>0 && abs($raw-round($raw))<0.001,'Las horas deben equivaler a minutos enteros positivos.');
        return (int)round($raw);
    }
    public function act(array $u,array $input,array $files): int {
        return atomic(function() use($u,$input,$files) {
            $number=trim($input['number']??''); $issued=$input['issued_on']??''; $expires=$input['expires_on']??'';
            ensure($number!=='' && mb_strlen($number)<=120,'Indique el número del acto (hasta 120 caracteres).');
            ensure(validDate($issued)&&validDate($expires)&&$issued<=$expires && $issued<=date('Y-m-d') && $expires>=date('Y-m-d'),'Revise las fechas de expedición y vencimiento.');
            ensure(count($files)>=1,'Adjunte el acto administrativo.');
            $id=insert('acts',['user_id'=>$u['id'],'number'=>$number,'issued_on'=>$issued,'expires_on'=>$expires,'granted_minutes'=>self::hourInput($input['hours']??''),'created_at'=>now()]);
            saveUploads($files,(int)$u['id'],null,$id);
            event((int)$u['id'],'Acto registrado','Acto '.$id.' · '.$number);
            foreach (rows('SELECT * FROM users WHERE active=1') as $admin) if (hasRole($admin,'payroll') && $admin['id']!==$u['id']) notify((int)$admin['id'],'Acto administrativo por revisar','Hay un acto administrativo pendiente de validación por Nómina.');
            return $id;
        });
    }
    public function reviewAct(array $admin,int $id,string $decision,string $comment): void {
        $current=row('SELECT * FROM users WHERE id=? AND active=1',[$admin['id']]);
        ensure($current!==null && hasRole($current,'payroll'),'La validación de actos administrativos requiere el rol de Nómina.');
        atomic(function() use($admin,$id,$decision,$comment) {
            $act=row('SELECT * FROM acts WHERE id=?',[$id]);
            ensure($act && $act['status']==='pending','El acto ya fue revisado o no existe.');
            ensure((int)$act['user_id']!==(int)$admin['id'],'No puede aprobar su propio acto. Debe revisarlo otro usuario de Nómina.');
            ensure(in_array($decision,['approved','rejected'],true),'Decisión no válida.');
            ensure(mb_strlen(trim($comment))>=5,'Registre una observación de la revisión.');
            ensure($decision!=='approved'||$act['expires_on']>=date('Y-m-d'),'No se puede aprobar un acto vencido.');
            run('UPDATE acts SET status=?,reviewed_by=?,reviewed_at=?,comment=? WHERE id=?',[$decision,$admin['id'],now(),$comment,$id]);
            event((int)$admin['id'],'Acto '.statusLabel($decision),'Acto '.$id.' · '.$comment);
            notify((int)$act['user_id'],'Revisión de acto administrativo',$act['number'].' · '.statusLabel($decision).' · '.$comment);
            captureBalances((int)$act['user_id']);
        });
    }
    public function review(array $actor,int $id,string $decision,string $comment): void {
        $this->reviewStep($actor,$id,$decision,$comment);
    }
    private function reviewStep(array $actor,int $id,string $decision,string $comment,?int $bulkApprovalId=null): void {
        atomic(function() use($actor,$id,$decision,$comment,$bulkApprovalId) {
            $r=row('SELECT * FROM requests WHERE id=?',[$id]);
            ensure($r && $r['status']==='pending','La solicitud no está pendiente de aprobación.');
            $step=row("SELECT * FROM approvals WHERE request_id=? AND revision=? AND status='waiting' ORDER BY step LIMIT 1",[$id,$r['revision']]);
            ensure($step && (int)$step['assigned_user_id']===(int)$actor['id'] && hasRole($actor,$step['role']) && (int)$r['user_id']!==(int)$actor['id'],'No le corresponde revisar esta etapa.');
            $currentActor=row('SELECT * FROM users WHERE id=? AND active=1',[$actor['id']]);
            ensure($currentActor!==null && hasRole($currentActor,$step['role']),'Su cuenta debe estar activa y conservar el rol de la etapa pendiente.');
            if ($step['designation_id']) {
                $assignment=row('SELECT * FROM designations WHERE id=?',[$step['designation_id']]);
                ensure($assignment && $assignment['start_date']<=date('Y-m-d') && in_array((int)$actor['id'],[(int)$assignment['user_id'],(int)$assignment['alternate_id']],true),'La designación todavía no ha comenzado o no le corresponde.');
            }
            if ($bulkApprovalId!==null) {
                ensure((int)$step['id']===$bulkApprovalId,'La etapa cambió o ya fue revisada. Actualice la bandeja.');
                ensure(in_array($step['role'],['manager','subsecretary'],true),'La aprobación masiva solo está disponible para jefe inmediato y subsecretario.');
                $currentActor=row('SELECT * FROM users WHERE id=? AND active=1',[$actor['id']]);
                ensure($currentActor!==null && hasRole($currentActor,$step['role']),'Su rol de aprobación cambió. Actualice la sesión.');
            }
            ensure(in_array($decision,['approved','returned','rejected'],true),'Decisión no válida.');
            if ($decision!=='approved') ensure(mb_strlen(trim($comment))>=5,'Explique el motivo de la devolución o rechazo.');
            run('UPDATE approvals SET status=?,acted_by=?,acted_at=?,comment=? WHERE id=?',[$decision,$actor['id'],now(),$comment,$step['id']]);
            $next=row("SELECT * FROM approvals WHERE request_id=? AND revision=? AND status='waiting' ORDER BY step LIMIT 1",[$id,$r['revision']]);
            $status=$decision==='approved'?($next?'pending':'approved'):$decision;
            if ($status==='approved') {
                // No se aprueban ausencias ya transcurridas. La excepción de anticipación permite enviar, no aprobar con retroactividad.
                $first=row('SELECT * FROM request_slots WHERE request_id=? ORDER BY date,start_time LIMIT 1',[$id]);
                ensure(new DateTimeImmutable($first['start_at']??($first['date'].' '.$first['start_time']))>new DateTimeImmutable(),'La fecha de inicio ya pasó. Devuelva la solicitud para actualizarla.');
                run("UPDATE allocations SET state='consumed' WHERE request_id=?",[$id]);
            }
            if ($status==='rejected') run("UPDATE allocations SET state='released',returned_minutes=minutes WHERE request_id=?",[$id]);
            if (in_array($status,['rejected','returned'],true)) run("UPDATE approvals SET status='skipped' WHERE request_id=? AND revision=? AND status='waiting'",[$id,$r['revision']]);
            run('UPDATE requests SET status=?,updated_at=? WHERE id=?',[$status,now(),$id]);
            event((int)$actor['id'],'Revisión: '.statusLabel($decision),roleLabel($step['role']).($bulkApprovalId!==null?' · Aprobación masiva':'').' · '.$comment,$id);
            notify((int)$r['user_id'],'Novedad de solicitud TH-'.$id,statusLabel($status).' · '.$comment);
            if ($decision==='approved' && $next) notify((int)$next['assigned_user_id'],'Solicitud pendiente TH-'.$id,'La etapa anterior fue aprobada. Puede revisar la solicitud.');
            captureBalances((int)$r['user_id']);
        });
    }
    public function approveMany(array $actor,array $approvalIds,string $comment=''): array {
        ensure(hasRole($actor,'manager') || hasRole($actor,'subsecretary'),'La aprobación masiva requiere rol de jefe inmediato o subsecretario.');
        ensure(count($approvalIds)>0 && count($approvalIds)<=100,'Seleccione entre una y 100 solicitudes.');
        $ids=[];
        foreach ($approvalIds as $value) {
            ensure((is_int($value)||is_string($value)) && filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])!==false,'La selección contiene una etapa no válida.');
            $ids[]=(int)$value;
        }
        $comment=trim($comment);ensure(mb_strlen($comment)<=3000,'La observación no puede superar 3.000 caracteres.');
        $result=['approved'=>[],'failed'=>[]];$seenRequests=[];
        foreach (array_unique($ids) as $approvalId) {
            $requestId=null;
            try {
                $selected=row('SELECT request_id FROM approvals WHERE id=? AND assigned_user_id=?',[$approvalId,$actor['id']]);
                ensure($selected!==null,'La etapa no existe o ya no está asignada a su usuario.');
                $requestId=(int)$selected['request_id'];
                ensure(!isset($seenRequests[$requestId]),'Solo puede aprobar una etapa por solicitud en cada lote.');
                $seenRequests[$requestId]=true;
                $this->reviewStep($actor,$requestId,'approved',$comment,$approvalId);
                $result['approved'][]=$requestId;
            } catch (DomainException $e) {
                $result['failed'][]=['approval_id'=>$approvalId,'request_id'=>$requestId,'message'=>$e->getMessage()];
            } catch (Throwable $e) {
                error_log((string)$e);
                $result['failed'][]=['approval_id'=>$approvalId,'request_id'=>$requestId,'message'=>'No se pudo guardar la aprobación. Intente nuevamente.'];
            }
        }
        return $result;
    }
    public function cancel(array $actor,int $id,string $reason): void {
        atomic(function() use($actor,$id,$reason) {
            $r=row('SELECT * FROM requests WHERE id=?',[$id]);
            ensure($r && (int)$r['user_id']===(int)$actor['id'],'No puede cancelar esta solicitud.');
            ensure(in_array($r['status'],['pending','returned','approved'],true),'La solicitud no admite cancelación en su estado actual.');
            ensure(mb_strlen(trim($reason))>=5,'Explique el motivo de la cancelación.');
            if ($r['status']==='approved') {
                $this->approver('subsecretary',$actor);
                run("UPDATE requests SET status='cancel_pending',cancellation_reason=?,cancellation_assignee_id=?,updated_at=? WHERE id=?",[$reason,$this->approver('subsecretary',$actor),now(),$id]);
                notify($this->approver('subsecretary',$actor),'Cancelación por revisar TH-'.$id,$reason);
            } else {
                run("UPDATE requests SET status='cancelled',cancellation_reason=?,cancelled_at=?,updated_at=? WHERE id=?",[$reason,now(),now(),$id]);
                run("UPDATE allocations SET state='released',returned_minutes=minutes WHERE request_id=?",[$id]);
                run("UPDATE approvals SET status='skipped' WHERE request_id=? AND status='waiting'",[$id]);
            }
            event((int)$actor['id'],'Cancelación solicitada',$reason,$id);
            captureBalances((int)$actor['id']);
        });
    }
    public function reviewCancellation(array $actor,int $id,bool $approve,string $comment,?DateTimeImmutable $clock=null): void {
        $clock??=new DateTimeImmutable();
        atomic(function() use($actor,$id,$approve,$comment,$clock) {
            $r=row('SELECT * FROM requests WHERE id=?',[$id]);
            ensure($r && $r['status']==='cancel_pending','No hay una cancelación pendiente.');
            $owner=row('SELECT * FROM users WHERE id=?',[$r['user_id']]);
            ensure((int)$actor['id']===(int)$r['cancellation_assignee_id'] && hasRole($actor,'subsecretary'),'Solo el subsecretario asignado al solicitar la cancelación puede resolverla.');
            ensure(mb_strlen(trim($comment))>=5,'Registre el motivo de su decisión.');
            if ($approve) {
                $used=0; foreach (rows('SELECT * FROM request_slots WHERE request_id=?',[$id]) as $s) $used+=$this->consumedSlot($s,$clock->format('Y-m-d H:i:s'));
                // Se distribuye el tiempo ya disfrutado por el orden de asignación de los actos.
                foreach (rows('SELECT * FROM allocations WHERE request_id=? ORDER BY id',[$id]) as $a) {
                    $consumed=min($used,(int)$a['minutes']); $used-=$consumed;
                    run("UPDATE allocations SET state='consumed',returned_minutes=? WHERE id=?",[(int)$a['minutes']-$consumed,$a['id']]);
                }
                run("UPDATE requests SET status='cancelled',cancelled_at=?,updated_at=? WHERE id=?",[$clock->format('Y-m-d H:i:s'),now(),$id]);
            } else run("UPDATE requests SET status='approved',updated_at=? WHERE id=?",[now(),$id]);
            event((int)$actor['id'],$approve?'Cancelación autorizada':'Cancelación denegada',$comment,$id);
            notify((int)$r['user_id'],'Resolución de cancelación TH-'.$id,($approve?'Cancelación autorizada; se reintegró únicamente el tiempo no disfrutado.':'La solicitud conserva su aprobación.').' '.$comment);
            captureBalances((int)$r['user_id']);
        });
    }
    public function refreshAssignments(int $admin): void {
        foreach (rows("SELECT a.*,r.user_id FROM approvals a JOIN requests r ON r.id=a.request_id WHERE a.status='waiting' AND r.status='pending' AND a.revision=r.revision") as $a) {
            if (in_array($a['role'],['manager','subsecretary'],true)) continue; // Cada radicación conserva sus responsables, incluso tras el relevo.
            $owner=row('SELECT * FROM users WHERE id=?',[$a['user_id']]); $new=$this->approver($a['role'],$owner);
            if ($new!==(int)$a['assigned_user_id']) {
                run('UPDATE approvals SET assigned_user_id=? WHERE id=?',[$new,$a['id']]);
                event($admin,'Aprobador actualizado','Etapa '.roleLabel($a['role']).' · '.$a['assigned_user_id'].' → '.$new,(int)$a['request_id']);
                notify($new,'Asignación actualizada TH-'.$a['request_id'],'Se le asignó una etapa pendiente de aprobación.');
            }
        }
    }
}
