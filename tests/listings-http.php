<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$base=getenv('APP_LISTINGS_TEST_URL')?:'http://127.0.0.1:8093';$root=dirname(__DIR__);$checks=0;$cookie=$root.'/.work/http-cookies-'.bin2hex(random_bytes(4));
function http(string $query,array $fields=[],bool $post=false):array{global $base,$cookie;$c=curl_init($base.'/?'.$query);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>30]);if($post){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$fields);} $response=curl_exec($c);if($response===false)throw new RuntimeException(curl_error($c));$info=curl_getinfo($c);$h=substr($response,0,$info['header_size']);$b=substr($response,$info['header_size']);return ['code'=>$info['http_code'],'headers'=>$h,'body'=>$b];}
function token(array $r):string{preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);return $m[1]??'';}
function ok(bool $condition,string $message):void{global $checks;if(!$condition)throw new RuntimeException('FALLO: '.$message);$checks++;echo 'OK '.$message."\n";}
function login(string $name):string{global $cookie,$root;$cookie=$root.'/.work/cookies-'.$name.'-'.bin2hex(random_bytes(3));$r=http('page=login');$r=http('page=login',['action'=>'login','csrf'=>token($r),'username'=>$name,'password'=>'PruebaLocal!9876'],true);ok($r['code']===302,'Ingreso '.$name);return token(http('page=dashboard'));}
if(!str_contains(http('page=login')['headers'],'X-TH-Test-Environment: isolated'))throw new RuntimeException('El servidor no es el entorno aislado de pruebas. Use tests/run.ps1.');
function listingCount(string $html,string $path):int{$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);return (new DOMXPath($dom))->query($path)->length;}
login('qa5');
foreach(['users','jobs','designations'] as $page){$r=http('page='.$page);ok($r['code']===200&&!str_contains($r['body'],'Fatal error')&&listingCount($r['body'],'//table/tbody/tr')===10,'Diez registros por HTTP: '.$page);}
$users=http('page=users')['body'];ok(listingCount($users,"//select[@name='q_job']/option")>12&&listingCount($users,"//select[@name='q_role']/option")===7,'Directorio presenta listas de cargos y roles');
foreach(['q_name=subsecretario','q_username=qa1','q_identification=PRUEBA-1','q_job=Jefe','q_role=payroll'] as $filter)ok(listingCount(http('page=users&'.$filter)['body'],'//table/tbody/tr')===1,'Filtro HTTP del directorio: '.$filter);
ok(listingCount(http('page=users&list_page=2')['body'],'//table/tbody/tr')===10&&listingCount(http('page=users&list_page=3')['body'],'//table/tbody/tr')===2,'Navegación de las tres páginas del directorio');
$jobs=http('page=jobs&q_job=listado%2001&q_code=001&q_grade=09')['body'];ok(listingCount($jobs,'//table/tbody/tr')===1&&str_contains($jobs,'Profesional de listado 01'),'Filtros combinados del catálogo');
ok(listingCount($jobs,"//select[@name='job_id']/option")===13,'Formulario de grados conserva todos los cargos al filtrar el catálogo');
$history=http('page=designations&q_role=manager&q_name=jefe&q_from=2025-01-04&q_to=2025-01-06')['body'];ok(listingCount($history,'//table/tbody/tr')===3,'Filtros combinados del historial por HTTP');
$acts=http('page=acts')['body'];ok(listingCount($acts,"//details[contains(@class,'balance-person')]")===10,'Actos presenta diez resoluciones agrupadas');
$acts=http('page=acts&q_number=RES-001')['body'];ok(listingCount($acts,'//details')===1&&listingCount($acts,'//article')===2&&!listingCount($acts,'//details[@open]'),'La resolución se presenta plegada con sus dos registros');
ok(listingCount($acts,"//form[@class='review-act']//input[@name='csrf']")===2,'Cada registro conserva su aprobación de Nómina con protección CSRF');
ok(str_contains($acts,'Resolución No. RES-001')&&listingCount($acts,"//input[@name='q_name']")===1&&listingCount($acts,"//input[@name='q_identification']")===1,'Nómina dispone de filtros y prefijo visual de resolución');
ok(listingCount(http('page=acts&q_identification=PRUEBA-8')['body'],'//article')===1,'Nómina consulta por cédula');
login('qa1');$own=http('page=acts&q_number=RES-001&q_name=Otro&q_identification=PRUEBA-8')['body'];
ok(listingCount($own,'//article')===1&&listingCount($own,"//input[@name='q_name']")===0&&listingCount($own,"//input[@name='q_identification']")===0,'Funcionario solo dispone del filtro de resolución y conserva sus actos propios');
ok(listingCount($own,"//form[@class='review-act']")===0,'Funcionario no recibe acciones de aprobación de actos');
foreach(['users','jobs','designations'] as $page)ok(str_contains(http('page='.$page)['body'],'requiere un administrador'),'Acceso administrativo restringido: '.$page);
echo $checks." comprobaciones HTTP de listados correctas.\n";
