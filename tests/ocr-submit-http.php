<?php
declare(strict_types=1);
require __DIR__.'/mysql-environment.php';
require __DIR__.'/../app/bootstrap.php';
installMysqlSchema(db());
insert('departments',['name'=>'Pruebas OCR aisladas']);
foreach(['[]','["manager"]','["subsecretary"]'] as $index=>$roles) fixtureUser(['name'=>'Prueba OCR '.($index+1),'identification'=>'QA-OCR-'.($index+1),'username'=>'ocrqa'.($index+1),'email'=>'ocrqa'.($index+1).'@example.invalid','password'=>password_hash('PruebaLocal!9876',PASSWORD_DEFAULT),'job_title'=>'Prueba','department_id'=>1,'roles'=>$roles,'active'=>1,'force_password'=>0]);
run('UPDATE person_employments SET manager_id=2 WHERE person_id=1');setSetting('subsecretary_id','3');
foreach(require ROOT.'/database/catalog.php' as $category)insert('categories',array_combine(['family','name','slug','supports','max_minutes','policy_confirmed','notes'],$category)+['supports_required'=>1]);
$category=row("SELECT id FROM categories WHERE slug='sufragar'")['id'];
$port=18191;$cookie=ROOT.'/.work/ocr-post-cookies-'.bin2hex(random_bytes(4));
$server=proc_open([PHP_BINARY,'-c',ROOT.'/.runtime/php/php.ini','-d','max_execution_time=30','-S','127.0.0.1:'.$port,'-t',ROOT.'/public',ROOT.'/public/router.php'],[0=>['file','NUL','r'],1=>['file',ROOT.'/.work/ocr-post-server.log','w'],2=>['file',ROOT.'/.work/ocr-post-server-error.log','w']],$pipes,ROOT);
ensure(is_resource($server),'No se inició el servidor aislado');
function postHttp(array $fields=[]):array {
    global $port,$cookie;
    $curl=curl_init('http://127.0.0.1:'.$port.'/?page='.($fields&&($fields['action']??'')==='submit'?'new':'login'));
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>170,CURLOPT_FOLLOWLOCATION=>false]);
    if($fields)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields]);
    $out=curl_exec($curl);if($out===false)throw new RuntimeException(curl_error($curl));
    return ['status'=>curl_getinfo($curl,CURLINFO_HTTP_CODE),'body'=>substr($out,curl_getinfo($curl,CURLINFO_HEADER_SIZE))];
}
function postToken(string $body):string {preg_match('/name="csrf" value="([^"]+)"/',$body,$m);return $m[1]??'';}
try {
    usleep(700000);$login=postHttp();$csrf=postToken($login['body']);
    ensure(postHttp(['action'=>'login','csrf'=>$csrf,'username'=>'ocrqa1','password'=>'PruebaLocal!9876'])['status']===302,'No se inició sesión de prueba');
    $curl=curl_init('http://127.0.0.1:'.$port.'/?page=new');curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie]);$body=curl_exec($curl);$csrf=postToken($body);
    $date=(new DateTimeImmutable('next monday'))->modify('+7 days')->format('Y-m-d');
    $fields=['action'=>'submit','csrf'=>$csrf,'category_id'=>$category,'justification'=>'Ensayo aislado de envío y OCR','exception_reason'=>'Excepción de prueba funcional de soporte','slots[0][date]'=>$date,'slots[0][mode]'=>'hours','slots[0][start]'=>'07:30','slots[0][end]'=>'08:30'];
    if(isset($argv[1])) {
        $start=microtime(true);$reply=postHttp($fields+['supports[0]'=>new CURLFile(realpath($argv[1]),'image/jpeg','certificado-prueba.jpeg')]);
        ensure($reply['status']===200 && !str_contains($reply['body'],'Fatal error'),'La validación no devolvió una página completa');
        $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($reply['body']);$xp=new DOMXPath($dom);
        ensure($xp->query('//*[@id="main"]/div[contains(concat(" ",normalize-space(@class)," ")," error ")]')->length>0,'El formulario no recibió el motivo de validación');
        ensure((int)row('SELECT COUNT(*) n FROM requests')['n']===0,'La solicitud rechazada dejó un registro parcial');
        echo 'OK POST con OCR real devolvió validación visible y sin registro parcial en '.round(microtime(true)-$start,1)." s, con límite inicial de PHP de 30 s.\n";
    }
    $blank=ROOT.'/.work/ocr-post-blank.png';$image=imagecreatetruecolor(500,150);imagefill($image,0,0,0xffffff);imagepng($image,$blank);imagedestroy($image);
    $reply=postHttp($fields+['supports[0]'=>new CURLFile($blank,'image/png','soporte-sin-texto.png')]);
    ensure($reply['status']===302,'El fallo de lectura impidió registrar para revisión manual');
    ensure((int)row('SELECT COUNT(*) n FROM requests')['n']===1 && (int)row('SELECT COUNT(*) n FROM attachments')['n']===1,'No se conservaron solicitud y soporte');
    $request=row('SELECT * FROM requests LIMIT 1');ensure(ElectoralCertificate::needsManual($request),'Falta revisión manual del soporte ilegible');
    echo "OK POST con soporte ilegible registra una sola solicitud, conserva el adjunto y exige revisión manual.\n";
} finally {proc_terminate($server);proc_close($server);}
