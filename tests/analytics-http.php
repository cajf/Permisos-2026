<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$base=getenv('APP_ANALYTICS_TEST_URL')?:'http://127.0.0.1:8089';$root=dirname(__DIR__);$checks=0;$cookie=$root.'/.work/http-cookies-'.bin2hex(random_bytes(4));
function http(string $query,array $fields=[],bool $post=false):array{global $base,$cookie;$c=curl_init($base.'/?'.$query);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>30]);if($post){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$fields);} $response=curl_exec($c);if($response===false)throw new RuntimeException(curl_error($c));$info=curl_getinfo($c);$h=substr($response,0,$info['header_size']);$b=substr($response,$info['header_size']);return ['code'=>$info['http_code'],'headers'=>$h,'body'=>$b];}
function token(array $r):string{preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);return $m[1]??'';}
function ok(bool $condition,string $message):void{global $checks;if(!$condition)throw new RuntimeException('FALLO: '.$message);$checks++;echo 'OK '.$message."\n";}
function login(string $name):string{global $cookie,$root;$cookie=$root.'/.work/cookies-'.$name.'-'.bin2hex(random_bytes(3));$r=http('page=login');$r=http('page=login',['action'=>'login','csrf'=>token($r),'username'=>$name,'password'=>'PruebaLocal!9876'],true);ok($r['code']===302,'Ingreso '.$name);return token(http('page=dashboard'));}
if(!str_contains(http('page=login')['headers'],'X-TH-Test-Environment: isolated'))throw new RuntimeException('El servidor no es el entorno aislado de pruebas. Use tests/run.ps1.');
foreach(['qa1','qa2','qa3','qa4','qa5'] as $name){
 login($name);
 ok(!str_contains(http('page=dashboard')['body'],'page=analytics"'),'Menú restringido para '.$name);
 foreach(['analytics','analytics_data','analytics_export'] as $page)ok(http('page='.$page)['code']===403,'Acceso directo denegado '.$page.' '.$name);
}
login('qa8');
$asset=curl_init($base.'/assets/analytics.js');curl_setopt($asset,CURLOPT_RETURNTRANSFER,true);$assetBody=curl_exec($asset);ok(curl_getinfo($asset,CURLINFO_HTTP_CODE)===200&&str_contains($assetBody,"'use strict'"),'JavaScript del tablero servido como recurso estático');
$r=http('page=analytics');ok($r['code']===200&&str_contains($r['body'],'analytics-board'),'Titular vigente accede al tablero');
ok(str_contains($r['headers'],'no-store')&&str_contains($r['headers'],'private'),'Tablero privado sin caché');
$r=http('page=analytics_data&year=all');$data=json_decode($r['body'],true,512,JSON_THROW_ON_ERROR);
ok($r['code']===200&&$data['counts']['total']>=2&&isset($data['counts']['licencia']),'JSON devuelve conteo de disfrute actualizado');
$r=http('page=analytics_export&year=all');ok($r['code']===200&&str_contains($r['headers'],'text/csv')&&str_contains($r['body'],'segundos_disfrutados'),'Exportación privada para Power BI');
ok(!str_contains($r['body'],'PRUEBA-')&&!str_contains($r['body'],'@ejemplo.invalid'),'CSV sin cédulas ni correos');
ok(http('page=analytics_data&year=2026%20OR%201=1')['code']!==200,'Filtros malformados no se ejecutan');
echo $checks." comprobaciones HTTP del tablero correctas.\n";

