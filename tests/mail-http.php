<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$base=getenv('APP_MAIL_TEST_URL')?:'http://127.0.0.1:8096';$root=dirname(__DIR__);$checks=0;$cookie=$root.'/.work/http-cookies-'.bin2hex(random_bytes(4));
function http(string $query,array $fields=[],bool $post=false):array{global $base,$cookie;$c=curl_init($base.'/?'.$query);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>30]);if($post){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$fields);} $response=curl_exec($c);if($response===false)throw new RuntimeException(curl_error($c));$info=curl_getinfo($c);$h=substr($response,0,$info['header_size']);$b=substr($response,$info['header_size']);return ['code'=>$info['http_code'],'headers'=>$h,'body'=>$b];}
function token(array $r):string{preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);return $m[1]??'';}
function ok(bool $condition,string $message):void{global $checks;if(!$condition)throw new RuntimeException('FALLO: '.$message);$checks++;echo 'OK '.$message."\n";}
function login(string $name):string{global $cookie,$root;$cookie=$root.'/.work/cookies-'.$name.'-'.bin2hex(random_bytes(3));$r=http('page=login');$r=http('page=login',['action'=>'login','csrf'=>token($r),'username'=>$name,'password'=>'PruebaLocal!9876'],true);ok($r['code']===302,'Ingreso '.$name);return token(http('page=dashboard'));}
if(!str_contains(http('page=login')['headers'],'X-TH-Test-Environment: isolated'))throw new RuntimeException('El servidor no es el entorno aislado de pruebas. Use tests/run.ps1.');
$csrf=login('qa5');$r=http('page=outbox');
ok($r['code']===200&&str_contains($r['body'],'API de Brevo')&&str_contains($r['body'],'pendiente de configurar'),'Pantalla administrativa reconoce API desactivada');
$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($r['body']);$xp=new DOMXPath($dom);
ok($xp->query("//form[input[@name='action' and @value='mail_test']]//button[@disabled]")->length===1,'No permite prueba sin remitente y clave');
ok($xp->query("//form[input[@name='action' and @value='mail_process']]//button[@disabled]")->length===1,'No permite procesamiento sin configurar');
$r=http('page=outbox',['csrf'=>$csrf,'action'=>'mail_test','test_recipient'=>'prueba@correo-pruebas.co'],true);ok(str_contains($r['body'],'Configure y active la API'),'Servidor bloquea intento manual de prueba sin activar');
$r=http('page=outbox',['csrf'=>$csrf,'action'=>'mail_process'],true);ok(str_contains($r['body'],'Configure y active la API'),'Servidor bloquea procesamiento sin activar');
ok(!str_contains(http('page=outbox')['body'],'clave-ficticia-sin-red'),'No revela claves API en el panel');
$csrf=login('qa1');ok(str_contains(http('page=outbox')['body'],'requiere un administrador'),'Funcionario no puede consultar la cola');
$r=http('page=outbox',['csrf'=>$csrf,'action'=>'mail_test','test_recipient'=>'prueba@correo-pruebas.co'],true);ok(str_contains($r['body'],'requiere un administrador'),'Funcionario no puede solicitar pruebas');
echo $checks." comprobaciones HTTP de correo correctas, sin envíos reales.\n";
