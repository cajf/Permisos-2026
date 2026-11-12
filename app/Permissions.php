<?php
declare(strict_types=1);
function systemRoles():array {return ['employee'=>'Funcionario','manager'=>'Jefe inmediato','subsecretary'=>'Subsecretario','mayor'=>'Alcalde','payroll'=>'Nómina','labor_records'=>'Historias Laborales','admin'=>'Administrador de Talento Humano','superadmin'=>'Superadministrador'];}
function isSuperadmin(array $u):bool {return in_array('superadmin',roles($u),true);}
function permissionPages():array {return array_combine(['dashboard','requests','new','request','inbox','acts','policies','balances','analytics','labor_records','users','jobs','import','designations','settings','calendar','reports','outbox','mail_templates','audit'],['Mi espacio','Mis solicitudes','Nueva solicitud','Detalle de solicitud','Bandeja de aprobación','Actos administrativos','Tipos y soportes','Consulta de saldos','Tableros','Historias Laborales','Directorio de funcionarios','Cargos y grados','Importación','Designaciones','Responsables institucionales','Calendario laboral','Reportes','Cola de correos','Plantillas de correo','Historial de actividad']);}
function permissionActions():array {return ['official_job_category'=>'jobs.edit','submit'=>'request.submit','review'=>'request.review','approve_bulk'=>'request.bulk','cancel'=>'request.cancel','review_cancel'=>'request.cancel_review','act'=>'act.create','review_act'=>'act.review','user'=>'users.edit','account_save'=>'users.edit','reset_password'=>'users.reset','department'=>'departments.edit','job'=>'jobs.edit','job_grade'=>'jobs.edit','designation'=>'designations.edit','settings'=>'settings.edit','schedule'=>'calendar.edit','school_break'=>'calendar.edit','holiday'=>'calendar.edit','delete_holiday'=>'calendar.edit','policy'=>'policies.edit','import_preview'=>'import.execute','import_commit'=>'import.execute','mail_template_save'=>'mail_templates.edit','mail_test'=>'mail.test','mail_process'=>'mail.process','permissions_save'=>'permissions.manage','labor_records_pdf'=>'resource.labor_records','labor_records_zip'=>'resource.labor_records'];}
function permissionResources():array {return ['pdf'=>'resource.pdf','download'=>'resource.attachments','template'=>'resource.import_template','import_passwords'=>'resource.initial_passwords','export'=>'resource.reports','analytics_data'=>'view.analytics','analytics_export'=>'resource.analytics','estimate'=>'request.submit','manual_pdf'=>'account.manual'];}
function permissionCatalog():array {
 $catalog=[];foreach(permissionPages() as $key=>$label)$catalog['view.'.$key]=['Vistas',$label];
 foreach(['request.submit'=>'Registrar y corregir solicitudes','request.review'=>'Revisar etapas asignadas','request.approve'=>'Aprobar solicitud','request.return'=>'Devolver para corrección','request.reject'=>'Rechazar solicitud','request.bulk'=>'Aprobar varias solicitudes','request.cancel'=>'Solicitar cancelación','request.cancel_review'=>'Resolver cancelación asignada','act.create'=>'Registrar acto administrativo','act.review'=>'Revisar actos (Nómina)','act.approve'=>'Aprobar acto','act.reject'=>'Rechazar acto','users.edit'=>'Crear y editar funcionarios','users.roles'=>'Asignar roles a funcionarios','users.reset'=>'Restablecer contraseñas','departments.edit'=>'Crear dependencias','jobs.edit'=>'Gestionar cargos, grados y categorías','designations.edit'=>'Registrar designaciones','settings.edit'=>'Configurar responsables','calendar.edit'=>'Modificar calendario y horarios','policies.edit'=>'Modificar duraciones y soportes','import.execute'=>'Importar funcionarios','mail_templates.edit'=>'Editar plantillas de correo','mail.test'=>'Registrar prueba de correo','mail.process'=>'Procesar correos pendientes'] as $key=>$label)$catalog[$key]=['Acciones',$label];
 foreach(['resource.pdf'=>'Descargar constancia PDF','resource.attachments'=>'Descargar soportes autorizados','resource.import_template'=>'Descargar plantilla de importación','resource.initial_passwords'=>'Descargar accesos iniciales','resource.labor_records'=>'Descargar constancias de Historias Laborales','resource.reports'=>'Exportar reportes','resource.analytics'=>'Exportar tablero para Power BI'] as $key=>$label)$catalog[$key]=['Recursos',$label];
 foreach(['data.requests_all'=>'Consultar solicitudes de todos los funcionarios','data.acts_all'=>'Consultar actos de todos los funcionarios','data.balances_all'=>'Consultar saldos de todas las dependencias','data.balances_filter'=>'Filtrar saldos por funcionario y dependencia'] as $key=>$label)$catalog[$key]=['Alcance de datos',$label];return $catalog;
}
function defaultRolePermissions(string $role):array {
 $base=['view.dashboard','view.requests','view.new','view.request','view.inbox','view.acts','view.policies','view.balances','request.submit','request.cancel','act.create','resource.pdf','resource.attachments'];
 if($role==='admin')return array_values(array_diff(array_keys(permissionCatalog()),['view.analytics','resource.analytics','view.labor_records','resource.labor_records','request.review','request.approve','request.return','request.reject','request.bulk','request.cancel_review','act.review','act.approve','act.reject']));
 if($role==='labor_records')return array_merge($base,['view.labor_records','resource.labor_records']);
 if(in_array($role,['manager','subsecretary','mayor'],true))$base=array_merge($base,['request.review','request.approve','request.return','request.reject','data.balances_filter']);
 if(in_array($role,['manager','subsecretary'],true))$base[]='request.bulk';
 if($role==='subsecretary')$base=array_merge($base,['view.analytics','resource.analytics','request.cancel_review','data.balances_all']);
 if($role==='payroll')$base=array_merge($base,['act.review','act.approve','act.reject','data.acts_all']);return $base;
}
function rolePermissions(string $role):array {
 $cacheKey=databaseIdentity().'|'.$role;if(isset($GLOBALS['role_permission_cache'][$cacheKey]))return $GLOBALS['role_permission_cache'][$cacheKey];
 $saved=setting('role_permissions_'.$role,'');$list=$saved===''?defaultRolePermissions($role):json_decode($saved,true);
 return $GLOBALS['role_permission_cache'][$cacheKey]=is_array($list)?array_values(array_intersect(array_keys(permissionCatalog()),$list)):[];
}
function can(array $actor,string $permission):bool {
 if(isSuperadmin($actor))return true;if($permission==='account.manual')return true;
 if(!isset(permissionCatalog()[$permission]))return false;
 foreach(roles($actor)?:['employee'] as $role)if(isset(systemRoles()[$role])&&in_array($permission,rolePermissions($role),true))return true;return false;
}
function requirePermission(array $actor,string $permission):void {ensure(can($actor,$permission),'No tiene permiso para esta acción o recurso ('.$permission.').');}
function authorizeSuperadmin(array $actor):void {ensure(isSuperadmin($actor),'Solo el superadministrador puede gestionar los permisos de los roles.');}
function saveRolePermissions(array $actor,array $input):void {
 authorizeSuperadmin($actor);$role=(string)($input['role']??'');ensure(isset(systemRoles()[$role])&&$role!=='superadmin','Seleccione un rol configurable. El superadministrador siempre tiene todos los permisos.');
 $selected=$input['permissions']??[];ensure(is_array($selected)&&count($selected)<=count(permissionCatalog()),'Lista de permisos no válida.');foreach($selected as $key)ensure(is_string($key)&&isset(permissionCatalog()[$key]),'Permiso no válido.');
 atomic(function()use($actor,$input,$role,$selected){
  ensure((string)($input['version']??'')===setting('role_permissions_version','0'),'Los permisos cambiaron en otra sesión. Recargue antes de guardar.');
  $before=rolePermissions($role);$selected=array_values(array_unique($selected));sort($selected);
  setSetting('role_permissions_'.$role,json_encode($selected,JSON_THROW_ON_ERROR));setSetting('role_permissions_version',(string)((int)setting('role_permissions_version','0')+1));
  unset($GLOBALS['role_permission_cache']);
  event((int)$actor['id'],'Permisos de rol actualizados',json_encode(['role'=>$role,'added'=>array_values(array_diff($selected,$before)),'removed'=>array_values(array_diff($before,$selected))],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 });
}
function authorizeRoute(array $actor,string $page):void {
 if(in_array($page,['password','manual'],true))return;if($page==='permissions'){authorizeSuperadmin($actor);return;}
 $key=permissionResources()[$page]??(isset(permissionPages()[$page])?'view.'.$page:'view.dashboard');requirePermission($actor,$key);
}
function authorizeAction(array $actor,string $action,array $input=[]):void {
 if(in_array($action,['logout','password'],true))return;
 $key=permissionActions()[$action]??'';if($key==='permissions.manage'){authorizeSuperadmin($actor);return;}requirePermission($actor,$key);
 if($action==='review')requirePermission($actor,['approved'=>'request.approve','returned'=>'request.return','rejected'=>'request.reject'][$input['decision']??'']??'');
 if($action==='approve_bulk')requirePermission($actor,'request.approve');
 if($action==='review_act')requirePermission($actor,['approved'=>'act.approve','rejected'=>'act.reject'][$input['decision']??'']??'');
}
/** Solo oculta controles; authorizeAction/authorizeRoute realizan la validación del servidor. */
function permissionControls(string $html,array $actor):string {
 $html=preg_replace_callback('~<form\b[^>]*>.*?</form>~si',function($m)use($actor){
  if(!preg_match('~name=["\']action["\']\s+value=["\']([^"\']+)~i',$m[0],$a))return $m[0];$action=$a[1];
  $key=permissionActions()[$action]??null;if($key&&!can($actor,$key))return '';
  return preg_replace_callback('~<button\b[^>]*>.*?</button>~si',function($b)use($actor,$action){
   if(in_array($action,['review','review_act'],true)&&preg_match('~value=["\'](approved|returned|rejected)["\']~',$b[0],$d)){
    $key=($action==='review'?'request.':'act.').['approved'=>'approve','returned'=>'return','rejected'=>'reject'][$d[1]];if(!can($actor,$key))return '';
   }return $b[0];
  },$m[0]);
 },$html);
 return preg_replace_callback('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>.*?</a>~si',function($m)use($actor){parse_str(parse_url(html_entity_decode($m[1]),PHP_URL_QUERY)??'',$query);if(isset($query['page'])){try{authorizeRoute($actor,(string)$query['page']);}catch(DomainException){return '';}}return $m[0];},$html);
}
