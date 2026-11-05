<?php
declare(strict_types=1);
$cookie=dirname(__DIR__).'/.work/catalog-cookie-'.bin2hex(random_bytes(5));$checks=0;
function callPage(string $page,array $fields=[]):array {global $cookie;$c=curl_init('http://127.0.0.1:8099/?page='.$page);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>20]);if($fields)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields)]);$raw=curl_exec($c);if($raw===false)throw new RuntimeException(curl_error($c));$info=curl_getinfo($c);return ['status'=>$info['http_code'],'headers'=>substr($raw,0,$info['header_size']),'body'=>substr($raw,$info['header_size'])];}
function token(array $r):string {preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);return $m[1]??'';}
function check(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo 'OK '.$message.PHP_EOL;}
check(str_contains(callPage('login')['headers'],'X-TH-Test-Environment: isolated'),'Servidor aislado');
$r=callPage('login');$r=callPage('login',['csrf'=>token($r),'action'=>'login','username'=>'qa5','password'=>'PruebaLocal!9876']);check($r['status']===302,'Ingreso Talento Humano');
$r=callPage('jobs');check(str_contains($r['body'],'name="employment_group"')&&str_contains($r['body'],'name="official_category_id"'),'Formulario de cargo ofrece grupo y categoría');
check(str_contains($r['body'],'value="official_job_category"'),'Formulario de administración de categorías disponible');
$csrf=token($r);$r=callPage('jobs',['csrf'=>$csrf,'action'=>'official_job_category','name'=>'Categoría HTTP']);check($r['status']===302&&str_contains(callPage('jobs')['body'],'Categoría HTTP'),'Guarda categoría desde HTTP');
$r=callPage('users');check(str_contains($r['body'],'data-group="official_worker"')&&str_contains($r['body'],'data-category="Categoría A"'),'Formulario de funcionarios expone el grupo y categoría del cargo');
$cookie.='-employee';$r=callPage('login');callPage('login',['csrf'=>token($r),'action'=>'login','username'=>'qa1','password'=>'PruebaLocal!9876']);$r=callPage('dashboard');
$r=callPage('jobs',['csrf'=>token($r),'action'=>'official_job_category','name'=>'No autorizado']);check(str_contains($r['body'],'No tiene permiso'),'Funcionario no puede registrar categorías por POST');
echo $checks." comprobaciones HTTP correctas.\n";
