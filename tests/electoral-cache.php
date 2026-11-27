<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$name=ROOT.'/.work/cache-sample-'.bin2hex(random_bytes(5)).'.png';$owner=random_int(1000000,2000000);$checks=0;
function cacheCheck(bool $ok,string $label):void {ensure($ok,$label);echo 'OK '.$label.PHP_EOL;$GLOBALS['checks']++;}
try {
    $image=imagecreatetruecolor(1800,700);imagefill($image,0,0,0xffffff);
    foreach(['REGISTRADURIA NACIONAL DEL ESTADO CIVIL','CERTIFICADO ELECTORAL','ELECCIONES 25 DE OCTUBRE DE 2026','CEDULA 1234567890'] as $index=>$line)imagettftext($image,30,0,40,90+$index*130,0,'C:/Windows/Fonts/arial.ttf',$line);
    imagepng($image,$name);imagedestroy($image);
    $file=['tmp'=>$name,'mime'=>'image/png','original_name'=>'MUESTRA-FICTICIA.png','sha256'=>hash_file('sha256',$name)];
    $first=ElectoralCertificate::readFiles([$file],'sufragar',$owner);
    cacheCheck($first['cache_hit']===false && $first['documents'][0]['recognized'],'La primera lectura ejecuta OCR');
    $second=ElectoralCertificate::readFiles([$file],'sufragar',$owner);
    cacheCheck($second['cache_hit']===true && $second['documents']===$first['documents'],'La repetición reutiliza la lectura sin alterarla');
    $other=ElectoralCertificate::readFiles([$file],'sufragar',$owner+1);
    cacheCheck($other['cache_hit']===false,'Otro funcionario no reutiliza la entrada privada');
    foreach([['999999999','2026-11-02'],['1234567890','2026-11-25']] as [$person,$date]) {
        $blocked=false;try{ElectoralCertificate::validate($second,$person,[['date'=>$date]],'2026-11-26');}catch(DomainException){$blocked=true;}
        cacheCheck($blocked,'El resultado reutilizado revalida identidad y plazo');
    }
    file_put_contents($name,"\n",FILE_APPEND);
    $changed=ElectoralCertificate::readFiles([$file],'sufragar',$owner);
    cacheCheck($changed['cache_hit']===false,'Calcula la huella del contenido real, incluso si el sha256 recibido es antiguo');
} finally {if(is_file($name))unlink($name);}
echo "$checks comprobaciones correctas, sin acceso a bases de datos.\n";
