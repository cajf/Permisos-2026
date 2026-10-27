<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$base='http://127.0.0.1:8089';$root=dirname(__DIR__);$checks=0;$cookie=$root.'/.work/http-cookies-'.bin2hex(random_bytes(4));
function http(string $query,array $fields=[],bool $post=false):array{global $base,$cookie;$c=curl_init($base.'/?'.$query);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>30]);if($post){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$fields);} $response=curl_exec($c);if($response===false)throw new RuntimeException(curl_error($c));$info=curl_getinfo($c);$h=substr($response,0,$info['header_size']);$b=substr($response,$info['header_size']);return ['code'=>$info['http_code'],'headers'=>$h,'body'=>$b];}
function token(array $r):string{preg_match('/name="csrf" value="([^"]+)"/',$r['body'],$m);return $m[1]??'';}
function ok(bool $condition,string $message):void{global $checks;if(!$condition)throw new RuntimeException('FALLO: '.$message);$checks++;echo 'OK '.$message."\n";}
function login(string $name):string{global $cookie,$root;$cookie=$root.'/.work/cookies-'.$name.'-'.bin2hex(random_bytes(3));$r=http('page=login');$r=http('page=login',['action'=>'login','csrf'=>token($r),'username'=>$name,'password'=>'PruebaLocal!9876'],true);ok($r['code']===302,'Ingreso '.$name);return token(http('page=dashboard'));}
if(!str_contains(http('page=login')['headers'],'X-TH-Test-Environment: isolated'))throw new RuntimeException('El servidor no es el entorno aislado de pruebas. Use tests/run.ps1.');
$pdf=new TCPDF();$pdf->setPrintHeader(false);$pdf->AddPage();$pdf->SetFont('helvetica','',16);$pdf->Write(10,'SOPORTE PDF DE PRUEBA');$pdf->Output($root.'/.work/soporte.pdf','F');
$img=imagecreatetruecolor(600,240);$white=imagecolorallocate($img,255,255,255);imagefill($img,0,0,$white);$green=imagecolorallocate($img,20,105,80);imagestring($img,5,30,100,'SOPORTE PNG DE PRUEBA',$green);imagepng($img,$root.'/.work/soporte.png');
$csrf=login('qa8');$employeeCookie=$cookie;
$bad=http('page=new',['action'=>'submit','csrf'=>'incorrecto'],true);ok(str_contains($bad['body'],'expiró'),'Protección CSRF');
$r=http('page=new',['action'=>'submit','csrf'=>$csrf,'category_id'=>'1','justification'=>'Prueba del formulario con anexos PDF e imagen','exception_reason'=>'Excepción para verificar el proceso local','slots[0][date]'=>'2026-12-10','slots[0][start]'=>'07:30','slots[0][end]'=>'09:30','slots[0][mode]'=>'hours','supports[0]'=>new CURLFile($root.'/.work/soporte.pdf','application/pdf','soporte.pdf'),'supports[1]'=>new CURLFile($root.'/.work/soporte.png','image/png','soporte.png')],true);
ok($r['code']===302 && preg_match('/Location: \/\?page=request&id=(\d+)/i',$r['headers'],$m)===1,'Registro por HTTP con carga multipart');$id=$m[1];
$detail=http('page=request&id='.$id);ok(str_contains($detail['body'],'En aprobación') && str_contains($detail['body'],'soporte.pdf'),'Detalle con estado y soportes');
$download=http('page=pdf&id='.$id);ok(str_starts_with($download['body'],'%PDF-'),'Generación real del PDF con anexos');file_put_contents($root.'/.work/solicitud-verificacion.pdf',$download['body']);
preg_match('/page=download&id=(\d+)/',$detail['body'],$attachment);ok(http('page=download&id='.$attachment[1])['code']===200,'Descarga autorizada de soporte');
$csrf=login('qa1');$denied=http('page=request&id='.$id);ok(str_contains($denied['body'],'No tiene acceso'),'Otro funcionario no puede consultar detalle');$denied=http('page=pdf&id='.$id);ok($denied['code']===403,'Otro funcionario no puede generar PDF');$denied=http('page=download&id='.$attachment[1]);ok($denied['code']===403,'Otro funcionario no puede descargar soporte');
$csrf=login('qa2');$inbox=http('page=inbox');ok(str_contains($inbox['body'],'id='.$id),'Solicitud visible en bandeja del jefe');
$r=http('page=request&id='.$id,['action'=>'review','csrf'=>$csrf,'id'=>$id,'decision'=>'approved','comment'=>'Prueba de aprobación por HTTP'],true);ok($r['code']===302,'Aprobación por HTTP');ok(str_contains(http('page=request&id='.$id)['body'],'Aprobada'),'Permiso de dos horas concluye sin subsecretario');
$csrf=login('qa5');foreach(['users','settings','calendar','policies','import','outbox','audit','reports'] as $page){$r=http('page='.$page);ok($r['code']===200 && !str_contains($r['body'],'Fatal error'),'Pantalla administrativa '.$page);}
$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();$sheet->fromArray([['cedula','nombre','usuario','correo','cargo','codigo','grado','vinculacion','dependencia','cedula_jefe','alto_rango'],['IMPORT-001','Importado de prueba','importado.qa','importado@ejemplo.invalid','Profesional','219','24','Carrera administrativa','Dependencia de prueba','PRUEBA-2','']]);(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($root.'/.work/importacion-prueba.xlsx');
$r=http('page=import',['action'=>'import_preview','csrf'=>$csrf,'excel'=>new CURLFile($root.'/.work/importacion-prueba.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','funcionarios.xlsx')],true);ok($r['code']===302,'Lectura de archivo XLSX');ok(str_contains(http('page=import')['body'],'Importado de prueba'),'Vista previa de importación');
$r=http('page=import',['action'=>'import_commit','csrf'=>$csrf],true);ok($r['code']===302,'Confirmación atómica de importación');ok(str_contains(http('page=users')['body'],'Importado de prueba'),'Funcionario importado visible en directorio');ok(str_contains(http('page=import_passwords')['body'],'importado.qa'),'Descarga privada de acceso inicial');
$report=http('page=export&from=2026-12-01&to=2026-12-31');ok(str_contains($report['body'],'radicado')&&str_contains($report['body'],'TH-'.$id),'Reporte CSV de solicitudes');
$r=http('page=dashboard');ok(str_contains($r['body'],'Mi espacio'),'Inicio administrativo disponible');
$policy=http('page=policies',['action'=>'policy','csrf'=>$csrf,'id'=>'9','supports'=>'','max_hours'=>'','notes'=>'Soporte opcional para prueba'],true);
ok($policy['code']===302,'Administrador puede guardar soporte opcional sin descripción');
$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML(http('page=policies')['body']);$xpath=new DOMXPath($dom);
$toggle=$xpath->query("//form[input[@name='id' and @value='9']]//input[@name='supports_required']");
ok($toggle->length===1 && !$toggle->item(0)->hasAttribute('checked'),'La opción de soporte opcional permanece guardada');
$csrf=login('qa8');
$new=http('page=new');$dom->loadHTML($new['body']);$xpath=new DOMXPath($dom);
ok($xpath->query("//select[@id='category']/optgroup/option[@value='9' and @data-supports-required='0']")->length===1,'Formulario recibe la configuración del tipo seleccionado');
$unauthorized=http('page=policies',['action'=>'policy','csrf'=>$csrf,'id'=>'9','supports_required'=>'1','supports'=>'Intento no autorizado'],true);
ok(str_contains($unauthorized['body'],'requiere un administrador'),'Solicitante no puede modificar obligatoriedad');
$withoutFiles=['action'=>'submit','csrf'=>$csrf,'category_id'=>'9','justification'=>'Solicitud con soporte opcional por configuración','slots[0][date]'=>'2026-12-15','slots[0][start]'=>'07:30','slots[0][end]'=>'08:30','slots[0][mode]'=>'hours'];
$optional=http('page=new',$withoutFiles,true);
ok($optional['code']===302 && preg_match('/Location: \/\?page=request&id=(\d+)/i',$optional['headers'],$optionalMatch)===1,'Solicitud opcional se envía sin archivos por HTTP');
$optionalPdf=http('page=pdf&id='.$optionalMatch[1]);
ok($optionalPdf['code']===200 && str_starts_with($optionalPdf['body'],'%PDF-'),'PDF disponible para una solicitud sin soportes');
$csrf=login('qa5');
$policy=http('page=policies',['action'=>'policy','csrf'=>$csrf,'id'=>'9','supports_required'=>'1','supports'=>'Registro de ingreso y salida en bicicleta.','max_hours'=>'','notes'=>'Obligatoriedad restablecida para prueba'],true);
ok($policy['code']===302,'Administrador puede volver a exigir soporte');
$csrf=login('qa8');$withoutFiles['csrf']=$csrf;$withoutFiles['slots[0][start]']='10:00';$withoutFiles['slots[0][end]']='11:00';
$required=http('page=new',$withoutFiles,true);
ok($required['code']===200 && str_contains($required['body'],'Adjunte al menos un soporte.'),'Servidor rechaza solicitud sin adjuntos cuando el tipo los exige');
file_put_contents($root.'/.work/http-result.json',json_encode(['checks'=>$checks,'request_id'=>$id,'completed'=>date(DATE_ATOM)],JSON_PRETTY_PRINT));echo "\n$checks comprobaciones HTTP correctas.\n";
