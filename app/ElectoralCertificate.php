<?php
declare(strict_types=1);

/** Lectura auxiliar local. No verifica autenticidad ante la Registraduría. */
final class ElectoralCertificate {
    public static function normalize(string $text): string {
        return strtoupper(strtr($text,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']));
    }
    public static function documentNumber(string $value): ?string {
        if (!preg_match('/^[0-9.\s]+$/D',trim($value))) return null;
        $digits=preg_replace('/\D/','',$value);
        return strlen($digits)>=4 && strlen($digits)<=12 ? ltrim($digits,'0') : null;
    }
    /** Solo extrae campos contextualizados en líneas legibles, sin corregir dígitos adivinados. */
    public static function parse(array $lines): array {
        $clear=[];
        foreach ($lines as $line) if (($line['confidence']??0)>=80) $clear[]=self::normalize((string)$line['text']);
        $text=implode("\n",$clear);
        $recognized=preg_match('/CERTIFICADO\s+ELECTORAL/',$text) && preg_match('/REGISTRADURIA/',$text) && preg_match('/NACIONAL|COLOMBIA/',$text);
        if (!$recognized) return ['recognized'=>false,'documents'=>[],'dates'=>[]];
        $numbers=[];$dates=[];
        foreach ($clear as $i=>$line) {
            // Un identificador de certificado, puesto o mesa nunca se toma como cédula.
            if (preg_match('/(?:CEDULA(?:\s+DE\s+CIUDADANIA)?|DOCUMENTO(?:\s+DE\s+IDENTIDAD)?|\bC\.?\s*C\.?)\s*(?:N[Oº°.]*)?\s*[:#-]?\s*([0-9][0-9. ]{2,22}[0-9])(?![0-9])/',$line,$m)) {
                if ($n=self::documentNumber($m[1])) $numbers[]=$n;
            }
            if (!preg_match('/ELECCIONES|FECHA\s+(?:DE\s+)?(?:LA\s+)?(?:ELECCION|VOTACION)/',$line)) continue;
            // La fecha puede continuar en la línea siguiente, pero no se usan fechas de expedición.
            $context=$line.' '.($clear[$i+1]??'');
            if (preg_match_all('/\b(\d{1,2})\s+DE\s+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SETIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+(?:DE[L]?\s+)?(20\d{2})\b/',$context,$matches,PREG_SET_ORDER)) {
                $months=['ENERO'=>1,'FEBRERO'=>2,'MARZO'=>3,'ABRIL'=>4,'MAYO'=>5,'JUNIO'=>6,'JULIO'=>7,'AGOSTO'=>8,'SEPTIEMBRE'=>9,'SETIEMBRE'=>9,'OCTUBRE'=>10,'NOVIEMBRE'=>11,'DICIEMBRE'=>12];
                foreach($matches as $m) if(checkdate($months[$m[2]],(int)$m[1],(int)$m[3])) $dates[]=sprintf('%04d-%02d-%02d',$m[3],$months[$m[2]],$m[1]);
            }
            if (preg_match_all('/\b(\d{1,2})[\/.-](\d{1,2})[\/.-](20\d{2})\b/',$context,$matches,PREG_SET_ORDER)) foreach($matches as $m) if(checkdate((int)$m[2],(int)$m[1],(int)$m[3])) $dates[]=sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);
        }
        return ['recognized'=>true,'documents'=>array_values(array_unique($numbers)),'dates'=>array_values(array_unique($dates))];
    }
    public static function fromTsv(string $tsv): array {
        $lines=[];
        foreach(explode("\n",$tsv) as $row) {
            $c=explode("\t",rtrim($row,"\r"),12);
            if(count($c)!==12 || $c[0]!=='5' || trim($c[11])==='') continue;
            $key=implode('-',array_slice($c,1,4));
            $lines[$key]['text']=($lines[$key]['text']??'').' '.$c[11];
            $lines[$key]['confidence']=min($lines[$key]['confidence']??100,(float)$c[10]);
        }
        return self::parse(array_values($lines));
    }
    private static function binary(string $name): string {
        $configured=$GLOBALS['config']['electoral_ocr'][$name]??'';
        if($configured!=='') return $configured;
        if(PHP_OS_FAMILY==='Windows') {
            if($name==='tesseract') return ROOT.'/.runtime/tesseract/tesseract.exe';
            $local=ROOT.'/.runtime/poppler/'.$name.'.exe';
            if(is_file($local)) return $local;
        }
        return $name;
    }
    /** Array de argumentos, sin shell; límites de tiempo y salida para documentos no confiables. */
    private static function command(array $args,string $dir,float $deadline): string {
        if(!function_exists('proc_open') || microtime(true)>=$deadline) throw new RuntimeException('OCR no disponible');
        $out=$dir.'/command.out';$err=$dir.'/command.err';
        $process=@proc_open($args,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$out,'w'],2=>['file',$err,'w']],$pipes,$dir,null,['bypass_shell'=>true,'suppress_errors'=>true]);
        if(!is_resource($process)) throw new RuntimeException('OCR no disponible');
        $exit=-1;
        try {
            do {
                $state=proc_get_status($process);
                if(!$state['running']) {$exit=$state['exitcode'];break;}
                clearstatcache(true,$out);
                clearstatcache(true,$err);
                if(microtime(true)>=$deadline || (is_file($out)&&filesize($out)>2*1024*1024) || (is_file($err)&&filesize($err)>2*1024*1024)) {proc_terminate($process);throw new RuntimeException('Límite de lectura OCR');}
                usleep(40000);
            } while(true);
        } finally {proc_close($process);}
        if($exit!==0) throw new RuntimeException('No se pudo leer el soporte con OCR');
        return (string)file_get_contents($out);
    }
    public static function readFiles(array $files): array {
        $result=['version'=>1,'status'=>'manual','checked_at'=>now(),'documents'=>[],'reasons'=>[]];
        $dir=ROOT.'/storage/cache/ocr-'.bin2hex(random_bytes(12));
        if(!@mkdir($dir,0700,true)) {$result['reasons'][]='No se pudo preparar la lectura del soporte.';return $result;}
        $deadline=microtime(true)+35;$pages=0;
        try {
            foreach($files as $index=>$file) {
                try {
                    if($pages>=6) throw new RuntimeException('Límite de páginas OCR');
                    $path=$file['tmp']??attachmentPath($file);
                    $images=[$path];
                    if($file['mime']==='application/pdf') {
                        $info=self::command([self::binary('pdfinfo'),$path],$dir,min($deadline,microtime(true)+5));
                        if(!preg_match('/^Pages:\s+(\d+)/m',$info,$m)) throw new RuntimeException('No se pudo contar el PDF');
                        if((int)$m[1]>3 || $pages+(int)$m[1]>6) throw new RuntimeException('El PDF supera el límite de lectura automática');
                        $prefix=$dir.'/page-'.$index;
                        self::command([self::binary('pdftoppm'),'-r','200','-scale-to','2400','-f','1','-l',$m[1],'-png',$path,$prefix],$dir,min($deadline,microtime(true)+10));
                        $images=glob($prefix.'-*.png');
                        if(!$images) throw new RuntimeException('No se pudo convertir el PDF');
                    }
                    foreach($images as $image) {
                        $pages++;
                        $tsv=self::command([self::binary('tesseract'),$image,'stdout','-l','spa','--psm','3','tsv'],$dir,min($deadline,microtime(true)+10));
                        $parsed=self::fromTsv($tsv);
                        $result['documents'][]=$parsed+['sha256'=>$file['sha256'],'file'=>$file['original_name']];
                    }
                } catch(Throwable $e) {
                    $result['reasons'][]='No fue posible completar la lectura automática de '.$file['original_name'].'. Revise su legibilidad, formato y contenido.';
                }
            }
        } finally {
            foreach(glob($dir.'/*')?:[] as $path) if(is_file($path)) @unlink($path);
            @rmdir($dir);
        }
        return $result;
    }
    /** Se ejecuta antes del bloqueo transaccional y nunca acepta resultados enviados por el navegador. */
    public static function prepare(array $owner,array $input,array $files,?int $id): ?array {
        $category=row('SELECT slug FROM categories WHERE id=?',[(int)($input['category_id']??0)]);
        if(($category['slug']??'')!=='sufragar') return null;
        if($id) {
            $old=row('SELECT user_id,status FROM requests WHERE id=?',[$id]);
            ensure($old && (int)$old['user_id']===(int)$owner['id'] && $old['status']==='returned','Solo puede corregir una solicitud propia devuelta.');
            $files=array_merge(rows('SELECT * FROM attachments WHERE request_id=?',[$id]),$files);
        }
        return self::readFiles($files);
    }
    public static function validate(array $report,string $identification,array $slots,?string $today=null): array {
        $expected=self::documentNumber($identification);$valid=[];
        foreach($report['documents'] as $document) {
            if(!$document['recognized']) continue;
            if(count($document['documents'])!==1 || count($document['dates'])!==1) $report['reasons'][]='Un certificado reconocido contiene datos incompletos o ambiguos; requiere revisión manual.';
            if(count($document['documents'])===1 && $expected!==null) ensure($document['documents'][0]===$expected,'El certificado electoral corresponde a un documento diferente al del solicitante. Cargue el certificado correcto.');
            if(count($document['dates'])===1) {
                $date=$document['dates'][0];
                ensure($date<=($today??date('Y-m-d')),'La fecha de elecciones leída es futura. Revise y cargue el certificado electoral correcto.');
                $first=(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
                $last=(new DateTimeImmutable($date))->modify('+30 days')->format('Y-m-d');
                foreach($slots as $slot) ensure($slot['date']>=$first && $slot['date']<=$last,'El compensatorio por sufragar debe disfrutarse entre '.$first.' y '.$last.' (los 30 días calendario siguientes a las elecciones).');
                if(count($document['documents'])===1 && $expected!==null) $valid[]=['election_date'=>$date,'document_number'=>$document['documents'][0],'sha256'=>$document['sha256']];
            }
        }
        $dates=array_unique(array_column($valid,'election_date'));
        if($valid && count($dates)===1 && !$report['reasons']) {
            $report['status']='verified';$report['election_date']=$valid[0]['election_date'];$report['document_number']=$valid[0]['document_number'];
            $report['valid_until']=(new DateTimeImmutable($report['election_date']))->modify('+30 days')->format('Y-m-d');
        } else {
            $report['status']='manual';
            $report['reasons'][]='No se identificaron inequívocamente el certificado electoral colombiano, la cédula y una fecha de elecciones coincidente. Es obligatoria la revisión manual del soporte y del plazo de disfrute.';
        }
        return $report;
    }
    public static function report(array $request): ?array {
        $snapshot=json_decode($request['snapshot']??'{}',true);
        return is_array($snapshot['electoral_check']??null)?$snapshot['electoral_check']:null;
    }
    public static function needsManual(array $request): bool {
        return (self::report($request)['status']??'manual')!=='verified';
    }
    public static function summary(array $report): string {
        return ($report['status']??'manual')==='verified'
            ?'Lectura OCR completada: cédula coincidente; elecciones '.$report['election_date'].'; disfrute permitido hasta '.$report['valid_until'].'. La lectura no certifica autenticidad.'
            :'REVISIÓN MANUAL OBLIGATORIA DEL CERTIFICADO ELECTORAL. '.implode(' ',array_unique($report['reasons']??['No existe una validación automática de este soporte.']));
    }
}
