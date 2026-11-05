<?php
declare(strict_types=1);
$base='http://127.0.0.1:8098';$root=dirname(__DIR__);$cookie=$root.'/.work/rbac-cookie-'.bin2hex(random_bytes(6));$checks=0;
function http(string $page,array $fields=[]):array {global $base,$cookie;$c=curl_init($base.'/?page='.$page);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>20]);if($fields)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields)]);$raw=curl_exec($c);if($raw===false)throw new RuntimeException(curl_error($c));$info=curl_getinfo($c);return ['code'=>$info['http_code'],'body'=>substr($raw,$info['header_size']),'headers'=>substr($raw,0,$info['header_size'])];}
function token(array $r):string {preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);return $m[1]??'';}
function check(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException('FALLO: '.$label);echo 'OK '.$label.PHP_EOL;$checks++;}
function login(string $name):string {global $cookie,$root;$cookie=$root.'/.work/rbac-cookie-'.bin2hex(random_bytes(6));$r=http('login');$r=http('login',['csrf'=>token($r),'action'=>'login','username'=>$name,'password'=>'PruebaLocal!9876']);check($r['code']===302,'Ingreso '.$name);return token(http('dashboard'));}
check(str_contains(http('login')['headers'],'X-TH-Test-Environment: isolated'),'Servidor de prueba aislado');
$csrf=login('qa7');$r=http('permissions');check(str_contains($r['body'],'Guardar permisos del rol'),'Superadministrador abre matriz');
$r=http('permissions',['action'=>'permissions_save','csrf'=>'invalido','role'=>'employee','version'=>'2']);check(str_contains($r['body'],'expiró'),'Protección CSRF');
$r=http('permissions',['action'=>'permissions_save','csrf'=>$csrf,'role'=>'employee','version'=>'2','permissions'=>['view.dashboard','view.jobs']]);check($r['code']===302,'Guarda permisos desde formulario');
$csrf=login('qa1');$r=http('jobs');check($r['code']===200&&str_contains($r['body'],'Catálogo')&&!str_contains($r['body'],'value="job"'),'Vista de lectura sin formularios de edición');
check(http('users')['code']===403,'URL directa a directorio denegada');
check(http('permissions')['code']===403,'URL directa a matriz denegada');
$r=http('permissions',['csrf'=>$csrf,'action'=>'permissions_save','role'=>'employee','version'=>'3']);check(str_contains($r['body'],'Solo el superadministrador'),'POST no permite autoasignarse permisos');
$r=http('jobs',['csrf'=>$csrf,'action'=>'job','title'=>'Cargo no autorizado','code'=>'99']);check(str_contains($r['body'],'No tiene permiso'),'POST directo no modifica cargos');
$csrf=login('qa5');check(http('permissions')['code']===403,'Talento Humano tampoco gestiona permisos');
$r=http('users',['csrf'=>$csrf,'action'=>'reset_password','id'=>'7','initial_password'=>'TemporalSoloPrueba123']);check(str_contains($r['body'],'Solo un superadministrador'),'Bloquea restablecimiento de contraseña del superadministrador');
echo $checks." comprobaciones HTTP de permisos correctas.\n";
