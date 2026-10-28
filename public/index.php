<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/Admin.php';
if ($GLOBALS['config']['trust_proxy'] && ($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https') $_SERVER['HTTPS']='on';
ini_set('session.use_strict_mode','1');
$sessionScope=substr(hash('sha256',databaseIdentity()),0,16);
$sessionDir=ROOT.'/storage/sessions/'.$sessionScope;
if(!is_dir($sessionDir))mkdir($sessionDir,0700,true);
session_save_path($sessionDir);
session_name('TH_'.$sessionScope);
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);
session_start();
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');
if(getenv('APP_TESTING')==='1')header('X-TH-Test-Environment: isolated');
try {
    if (!tableExists('users')) { http_response_code(503); exit('Inicialice la aplicación con scripts/install.php. Consulte README.md.'); }
    initializeBalanceHistory();
    $generation=setting('session_generation');
    if (($_SESSION['generation']??null)!==$generation) {
        $_SESSION=[];session_regenerate_id(true);$_SESSION['generation']=$generation;
    }
} catch (PDOException $exception) {
    error_log('MySQL no disponible al iniciar el portal: '.$exception->getCode());
    http_response_code(503);header('Content-Type: text/plain; charset=utf-8');
    exit(getenv('APP_CLOUD')==='1'
        ?'No se pudo conectar con MySQL. Revise la disponibilidad de la base y las variables privadas de conexión en el panel del alojamiento.'
        :'No se pudo conectar con MySQL. Inicie la aplicación con iniciar.cmd y espere la comprobación de conexión. Si el problema continúa, revise storage/mysql/server-error.log.');
}
if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>1800) { $_SESSION=[]; session_regenerate_id(true); }
$_SESSION['last_seen']=time(); $_SESSION['csrf']??=bin2hex(random_bytes(32));
$page=(string)($_GET['page']??'dashboard'); $wf=new Workflow(); $error=null;
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        ensure(isset($_POST['csrf']) && hash_equals($_SESSION['csrf'],(string)$_POST['csrf']),'La sesión del formulario expiró. Recargue la página.');
        $action=(string)($_POST['action']??'');
        if ($action==='login') {
            ensure((int)($_SESSION['login_lock']??0)<time(),'Espere unos minutos antes de intentar ingresar nuevamente.');
            $candidate=row('SELECT * FROM users WHERE username=?',[trim($_POST['username']??'')]);
            $valid=password_verify($_POST['password']??'',$candidate['password']??'$2y$10$hX00fAKXNV5VvepSg4J1Pev92FyeTwPa1bUKSfNWpOqAelgeT/JES');
            if (!$candidate || !$valid || !(int)$candidate['active'] || ($candidate['locked_until'] && $candidate['locked_until']>now())) {
                if ($candidate) run('UPDATE users SET failures=failures+1,locked_until=CASE WHEN failures>=7 THEN ? ELSE locked_until END WHERE id=?',[date('Y-m-d H:i:s',time()+900),$candidate['id']]);
                $_SESSION['login_failures']=(int)($_SESSION['login_failures']??0)+1;
                if ($_SESSION['login_failures']>=8) $_SESSION['login_lock']=time()+900;
                throw new DomainException('No se pudo iniciar sesión. Revise sus datos o inténtelo más tarde.');
            }
            session_regenerate_id(true); $_SESSION['uid']=$candidate['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); unset($_SESSION['login_failures'],$_SESSION['login_lock']);
            run('UPDATE users SET failures=0,locked_until=NULL WHERE id=?',[$candidate['id']]);
            redirect('/?page='.($candidate['force_password']?'password':'dashboard'));
        }
        $u=user();
        if ($action==='logout') { $_SESSION=[]; session_destroy(); redirect('/?page=login'); }
        if ($action==='password') {
            ensure(password_verify($_POST['current_password']??'',$u['password']),'La contraseña actual no coincide.');
            $new=$_POST['new_password']??''; validatePassword($new);
            ensure($new===($_POST['confirm_password']??''),'La confirmación no coincide.');
            ensure(!password_verify($new,$u['password']),'La nueva contraseña debe ser diferente.');
            run('UPDATE users SET password=?,force_password=0 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);
            session_regenerate_id(true); event((int)$u['id'],'Contraseña actualizada','Cambio realizado por el titular'); $_SESSION['flash']='Contraseña actualizada.'; redirect('/');
        }
        ensure(!(int)$u['force_password'],'Debe cambiar su contraseña antes de continuar.');
        $destination='/?page='.$page;
        switch ($action) {
            case 'submit':
                $id=$wf->submit($u,$_POST,validateUploads($_FILES['supports']??[]),empty($_POST['id'])?null:(int)$_POST['id']); $destination='/?page=request&id='.$id; break;
            case 'review': $wf->review($u,(int)$_POST['id'],$_POST['decision']??'',trim($_POST['comment']??'')); $destination='/?page=request&id='.(int)$_POST['id']; break;
            case 'approve_bulk':
                ensure(is_array($_POST['approval_ids']??null),'Seleccione las solicitudes que desea aprobar.');
                $_SESSION['bulk_result']=$wf->approveMany($u,$_POST['approval_ids'],(string)($_POST['comment']??''));
                redirect('/?page=inbox&inbox_page='.max(1,(int)($_POST['inbox_page']??1)));
            case 'cancel': $wf->cancel($u,(int)$_POST['id'],trim($_POST['comment']??'')); $destination='/?page=request&id='.(int)$_POST['id']; break;
            case 'review_cancel': $wf->reviewCancellation($u,(int)$_POST['id'],($_POST['decision']??'')==='approve',trim($_POST['comment']??'')); $destination='/?page=request&id='.(int)$_POST['id']; break;
            case 'act': $wf->act($u,$_POST,validateUploads($_FILES['supports']??[])); $destination='/?page=acts'; break;
            case 'review_act': $wf->reviewAct($u,(int)$_POST['id'],$_POST['decision']??'',trim($_POST['comment']??'')); $destination='/?page=acts'; break;
            case 'user': saveUser($u,$_POST,empty($_POST['id'])?null:(int)$_POST['id']); $destination='/?page=users'; break;
            case 'designation': saveDesignation($u,$_POST); $destination='/?page=designations'; break;
            case 'reset_password':
                authorizeAdmin($u); validatePassword($_POST['initial_password']??'');
                ensure(row('SELECT id FROM users WHERE id=?',[(int)$_POST['id']])!==null,'Funcionario no encontrado.');
                run('UPDATE users SET password=?,force_password=1,failures=0,locked_until=NULL WHERE id=?',[password_hash($_POST['initial_password'],PASSWORD_DEFAULT),(int)$_POST['id']]);
                event((int)$u['id'],'Contraseña restablecida','Funcionario '.(int)$_POST['id']); $destination='/?page=users'; break;
            case 'department': authorizeAdmin($u); ensure(trim($_POST['name']??'')!=='','Indique el nombre de la dependencia.'); insert('departments',['name'=>trim($_POST['name'])]); $destination='/?page=users'; break;
            case 'settings':
                authorizeAdmin($u);
                atomic(function() use($u,$wf) {
                    foreach (['mayor'] as $role) foreach (['_id','_alternate_id'] as $suffix) {
                        $id=(int)($_POST[$role.$suffix]??0);
                        if ($id) { $p=row('SELECT * FROM users WHERE id=? AND active=1',[$id]); ensure($p && hasRole($p,$role),'Seleccione un responsable activo con el rol correspondiente.'); }
                        else ensure($suffix==='_alternate_id','Debe asignar los responsables principales.');
                        setSetting($role.$suffix,(string)$id);
                    }
                    $wf->refreshAssignments((int)$u['id']); event((int)$u['id'],'Responsables actualizados','Se conservaron las actuaciones ya realizadas.');
                }); $destination='/?page=settings'; break;
            case 'schedule': saveSchedule($u,$_POST); $destination='/?page=calendar'; break;
            case 'holiday':
                authorizeAdmin($u); ensure(validDate($_POST['date']??'') && trim($_POST['name']??'')!=='','Indique fecha y nombre del día no laborable.');
                ensure($_POST['date']>=date('Y-m-d'),'No se modifican festivos pasados.');
                run('INSERT INTO holidays(date,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=?',[$_POST['date'],trim($_POST['name']),trim($_POST['name'])]); event((int)$u['id'],'Calendario actualizado',$_POST['date'].' · '.$_POST['name']); $destination='/?page=calendar'; break;
            case 'delete_holiday': authorizeAdmin($u); ensure(($_POST['date']??'')>=date('Y-m-d'),'No se modifican festivos pasados.'); run('DELETE FROM holidays WHERE date=?',[$_POST['date']]); event((int)$u['id'],'Festivo retirado',$_POST['date']); $destination='/?page=calendar'; break;
            case 'policy':
                authorizeAdmin($u);
                $supportsRequired=isset($_POST['supports_required'])?1:0;
                $supports=trim($_POST['supports']??'');
                ensure(!$supportsRequired || $supports!=='','Describa los soportes requeridos.');
                $max=trim($_POST['max_hours']??'')!==''?Workflow::hourInput($_POST['max_hours']):null;
                $c=row('SELECT * FROM categories WHERE id=?',[(int)$_POST['id']]); ensure($c!==null,'Tipo no válido.');
                if (in_array($c['slug'],['ordinario','cumpleanos'],true)) $max=null;
                atomic(function() use($supports,$supportsRequired,$max,$c,$u) {
                    run('UPDATE categories SET supports=?,supports_required=?,max_minutes=?,policy_confirmed=?,notes=? WHERE id=?',[$supports,$supportsRequired,$max,isset($_POST['policy_confirmed'])?1:0,trim($_POST['notes']??''),$c['id']]);
                    event((int)$u['id'],'Matriz actualizada',$c['name'].' · Soporte '.($supportsRequired?'obligatorio':'opcional'));
                }); $destination='/?page=policies'; break;
            case 'import_preview':
                authorizeAdmin($u); $f=$_FILES['excel']??[];
                ensure(($f['error']??1)===UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) && $f['size']<=5*1024*1024,'Adjunte un XLSX o CSV de hasta 5 MB.');
                $_SESSION['import']=readImport($f['tmp_name'],strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))); $destination='/?page=import'; break;
            case 'import_commit':
                authorizeAdmin($u); ensure(!empty($_SESSION['import']),'Prepare primero la importación.');
                $_SESSION['import_passwords']=commitImport($u,$_SESSION['import']); unset($_SESSION['import']); $destination='/?page=import'; break;
            default: throw new DomainException('Acción desconocida.');
        }
        $_SESSION['flash']='La operación se guardó correctamente.'; redirect($destination);
    }
} catch (DomainException $ex) { $error=$ex->getMessage(); }
catch (Throwable $ex) { error_log((string)$ex); $error=$ex instanceof PDOException && (($ex->errorInfo[1]??0)===1062)?'Ya existe un registro con esa cédula, usuario o número.':'No se pudo completar la operación. Revise los datos e inténtelo nuevamente.'; }

if ($page!=='login') {
    $u=user(); if ((int)$u['force_password'] && $page!=='password') redirect('/?page=password');
    try {
        if ($page==='manual_pdf') {
            ensure(is_file(ROOT.'/docs/manual.pdf'),'El manual PDF aún no está disponible.');
            header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="manual-talento-humano.pdf"');readfile(ROOT.'/docs/manual.pdf');exit;
        }
        if ($page==='estimate') {
            header('Content-Type: application/json; charset=utf-8');
            ensure(empty($_GET['slot']),'Actualice el formulario: use la jornada laboral configurada.');
            $date=$_GET['date']??''; $p=$wf->calendar->periods($date); $a=$_GET['start']??($p[0][0]??''); $b=$_GET['end']??($p[count($p)-1][1]??'');
            echo json_encode(['periods'=>$p,'minutes'=>$p?$wf->calendar->minutes($date,$a,$b):0],JSON_UNESCAPED_UNICODE); exit;
        }
        if (in_array($page,['pdf','download'],true)) {
            if ($page==='pdf') {
                $r=row('SELECT * FROM requests WHERE id=?',[(int)($_GET['id']??0)]); ensure($r && canReadRequest($u,$r),'No tiene acceso a esta solicitud.');
                $bytes=requestPdf($r); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="TH-'.(int)$r['id'].'.pdf"'); header('Content-Length: '.strlen($bytes)); echo $bytes; exit;
            }
            $a=row('SELECT * FROM attachments WHERE id=?',[(int)($_GET['id']??0)]); ensure($a!==null,'Soporte no encontrado.');
            $allowed=(int)$a['user_id']===(int)$u['id'] || hasRole($u,'admin') || ($a['act_id'] && hasRole($u,'payroll'));
            if ($a['request_id']) $allowed=canReadRequest($u,row('SELECT * FROM requests WHERE id=?',[$a['request_id']]));
            if ($a['act_id'] && !$allowed) foreach (rows('SELECT r.* FROM requests r JOIN allocations l ON l.request_id=r.id WHERE l.act_id=?',[$a['act_id']]) as $r) if(canReadRequest($u,$r)) $allowed=true;
            ensure($allowed,'No tiene acceso a este soporte.');
            $attachmentFile=attachmentPath($a);
            header('Content-Type: '.$a['mime']); header("Content-Disposition: attachment; filename=\"soporte\"; filename*=UTF-8''".rawurlencode($a['original_name'])); readfile($attachmentFile); exit;
        }
        if ($page==='template') {
            authorizeAdmin($u); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="plantilla-funcionarios.csv"');
            echo "\xEF\xBB\xBFcedula;nombre;usuario;correo;cargo;codigo;grado;vinculacion;dependencia;cedula_jefe;alto_rango;fecha_nacimiento\r\n"; exit;
        }
        if ($page==='import_passwords') {
            authorizeAdmin($u); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="accesos-iniciales.csv"'); $out=fopen('php://output','w');
            fputcsv($out,['usuario','contrasena_temporal'],';','"',''); foreach ($_SESSION['import_passwords']??[] as $p) fputcsv($out,$p,';','"',''); fclose($out); exit;
        }
        if ($page==='export') {
            authorizeAdmin($u); $from=$_GET['from']??date('Y-01-01'); $to=$_GET['to']??date('Y-12-31'); ensure(validDate($from)&&validDate($to)&&$from<=$to,'Revise las fechas del reporte.');
            header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="solicitudes.csv"'); echo "\xEF\xBB\xBF"; $out=fopen('php://output','w');
            fputcsv($out,['radicado','funcionario','cedula','dependencia','tipo','estado','minutos','fecha_solicitada','inicio','fin','registro'],';','"','');
            foreach (rows('SELECT r.*,u.name,u.identification,d.name department,c.name category,s.date,s.start_time,s.end_time,s.minutes FROM requests r JOIN users u ON u.id=r.user_id JOIN departments d ON d.id=u.department_id JOIN categories c ON c.id=r.category_id JOIN request_slots s ON s.request_id=r.id WHERE s.date BETWEEN ? AND ? ORDER BY r.id,s.date',[$from,$to]) as $r) {
                $values=['TH-'.$r['id'],$r['name'],$r['identification'],$r['department'],$r['category'],statusLabel($r['status']),$r['minutes'],$r['date'],$r['start_time'],$r['end_time'],$r['created_at']];
                $values=array_map(fn($v)=>preg_match('/^[=+\-@\t\r]/',(string)$v)?"'".$v:$v,$values); fputcsv($out,$values,';','"','');
            } fclose($out); exit;
        }
    } catch (DomainException $ex) { http_response_code(403); $error=$ex->getMessage(); $page='dashboard'; }
    catch (Throwable $ex) { error_log((string)$ex); $error='No se pudo generar el archivo. Contacte al administrador.'; $page='dashboard'; }
}
require ROOT.'/views/layout.php';
