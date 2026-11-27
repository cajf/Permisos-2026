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
    public static function parse(array $lines,string $kind='sufragar',?array $observedLines=null): array {
        $readFields=self::observedFields($observedLines??array_column($lines,'text'));
        $clear=[];$numberSafe=[];
        foreach ($lines as $line) if (($line['confidence']??0)>=80) {
            $clear[]=self::normalize((string)$line['text']);
            $numberSafe[]=empty($line['number_uncertain']);
        }
        $text=implode("\n",$clear);
        $recognized=preg_match('/CERTIFICADO\s+ELECTORAL/',$text) && preg_match('/REGISTRADURIA/',$text) && preg_match('/NACIONAL|COLOMBIA/',$text);
        if($kind==='jurado') $recognized=preg_match('/CERTIFICADO|CONSTANCIA/',$text) && preg_match('/JURADO(?:S)?\s+(?:DE\s+)?VOTACION/',$text) && preg_match('/PREST|SERVICIO|CUMPLIO|EJERCIO/',$text) && preg_match('/REGISTRADURIA/',$text);
        $numbers=[];$dates=[];
        foreach ($clear as $i=>$line) {
            // Un identificador de certificado, puesto o mesa nunca se toma como cédula.
            if ($numberSafe[$i] && preg_match('/(?:CEDULA(?:\s+DE\s+CIUDADANIA)?|DOCUMENTO(?:\s+DE\s+IDENTIDAD)?|\bC\.?\s*C\.?|^\s*NUM(?:ERO)?\.?)\s*(?:N[Oº°.]*)?\s*[:#-]?\s*([0-9][0-9. ]{2,22}[0-9])(?![0-9])/',$line,$m)) {
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
        $numbers=array_values(array_unique($numbers));$dates=array_values(array_unique($dates));
        return ['recognized'=>(bool)$recognized,'documents'=>$recognized?$numbers:[],'dates'=>$recognized?$dates:[],'read_fields'=>$readFields,
            'field_status'=>['certificate_type'=>$recognized?'read':'missing','document_number'=>count($numbers)===1?'read':($numbers?'ambiguous':'missing'),'election_date'=>count($dates)===1?'read':($dates?'ambiguous':'missing')]];
    }
    /** Texto observado para diagnóstico, incluso dudoso. Nunca alimenta la validación. */
    private static function observedFields(array $lines):array {
        $fields=['certificate_type'=>[],'document_number'=>[],'election_date'=>[]];
        $patterns=[
            'certificate_type'=>'/\b(?:CERTIFICADO|CONSTANCIA)\b.*?(?=\b(?:CEDULA|CÉDULA|DOCUMENTO|ELECCIONES|FECHA|NOMBRES?)\b|$)/iu',
            'document_number'=>'/(?:\bC[ÉE]DULA\b|\bDOCUMENTO\b|\bC\.?\s*C\.?\s|^\s*N[ÚU]M(?:ERO)?\.?).*?(?=\b(?:NOMBRES?|APELLIDOS?|MESA|PUESTO|ZONA|ELECCIONES|FECHA)\b|$)/iu',
            'election_date'=>'/(?:\bELECCIONES\b|\bFECHA\s+(?:DE\s+)?(?:LA\s+)?(?:ELECCI[ÓO]N|VOTACI[ÓO]N)\b).*?(?=\b(?:C[ÉE]DULA|DOCUMENTO|NOMBRES?|EXPEDICI[ÓO]N)\b|$)/iu',
        ];
        foreach($lines as $index=>$text) foreach($patterns as $field=>$pattern) {
            if(preg_match($pattern,(string)$text,$match)) {
                $value=trim($match[0]);
                // Los campos pueden estar debajo de su etiqueta en otro renglón.
                if($field!=='certificate_type' && !preg_match('/[0-9]/',$value) && isset($lines[$index+1]) && preg_match('/^\s*[0-9]/',(string)$lines[$index+1])) $value.=' '.trim($lines[$index+1]);
                if($value!=='')$fields[$field][]=mb_substr($value,0,240);
            }
        }
        foreach($fields as &$values)$values=array_values(array_unique($values));
        return $fields;
    }
    public static function fromTsv(string $tsv,string $kind='sufragar'): array {
        $lines=[];$previous='';$fragment=0;$uncertain=[];$words=[];
        foreach(explode("\n",$tsv) as $row) {
            $c=explode("\t",rtrim($row,"\r"),12);
            if(count($c)!==12 || $c[0]!=='5' || trim($c[11])==='') continue;
            $line=implode('-',array_slice($c,1,4));
            $words[$line][]=['text'=>$c[11],'confidence'=>(float)$c[10]];
            // Cortar el fragmento impide unir dígitos separados por una palabra dudosa.
            if($line!==$previous || (float)$c[10]<80) $fragment++;
            $previous=$line;
            if((float)$c[10]<80) {$uncertain[$line]=true;continue;}
            $key=$line.'-'.$fragment;
            $lines[$key]['text']=($lines[$key]['text']??'').' '.$c[11];
            $lines[$key]['confidence']=min($lines[$key]['confidence']??100,(float)$c[10]);
            $lines[$key]['source_line']=$line;
        }
        foreach($lines as &$line) $line['number_uncertain']=isset($uncertain[$line['source_line']]);
        unset($line);
        // Evaluar cada campo por separado: una mancha en el nombre no invalida
        // una cédula legible. Nunca eliminar un token dudoso dentro del campo.
        foreach($words as $tokens) {
            $count=count($tokens);
            for($i=0;$i<$count;$i++) {
                $label=self::normalize($tokens[$i]['text']);
                if(!preg_match('/^(?:CEDULA|DOCUMENTO|C\.?C\.?|NUM(?:ERO)?\.?|ELECCIONES|FECHA)[:.]?$/D',$label)) continue;
                $field=[];
                for($j=$i;$j<$count;$j++) {
                    if($j>$i && preg_match('/^(?:NOMBRES?|APELLIDOS?|MESA|PUESTO|ZONA|EXPEDICION|CEDULA|DOCUMENTO|ELECCIONES)[:.]?$/D',self::normalize($tokens[$j]['text']))) break;
                    $field[]=$tokens[$j];
                }
                if(min(array_column($field,'confidence'))<80) continue;
                $lines[]=['text'=>implode(' ',array_column($field,'text')),'confidence'=>min(array_column($field,'confidence'))];
            }
        }
        $observed=array_map(fn($tokens)=>implode(' ',array_column($tokens,'text')),array_values($words));
        return self::parse(array_values($lines),$kind,$observed);
    }
    private static function binary(string $name): string {
        $configured=$GLOBALS['config']['electoral_ocr'][$name]??'';
        if($configured!=='') return $configured;
        if($name==='python') return PHP_OS_FAMILY==='Windows'?ROOT.'/.runtime/ocr/Scripts/python.exe':'/opt/ocr/bin/python';
        if(PHP_OS_FAMILY==='Windows') {
            if($name==='tesseract') return ROOT.'/.runtime/tesseract/tesseract.exe';
            $local=ROOT.'/.runtime/poppler/'.$name.'.exe';
            if(is_file($local)) return $local;
        }
        return $name;
    }
    /** Array de argumentos, sin shell; límites de tiempo y salida para documentos no confiables. */
    private static function command(array $args,string $dir,float $deadline): string {
        if(!function_exists('proc_open')) throw new RuntimeException('La ejecución de procesos OCR está deshabilitada.');
        if(microtime(true)>=$deadline) throw new RuntimeException('Se agotó el tiempo máximo de lectura.');
        $out=$dir.'/command.out';$err=$dir.'/command.err';
        $process=@proc_open($args,[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$out,'w'],2=>['file',$err,'w']],$pipes,$dir,null,['bypass_shell'=>true,'suppress_errors'=>true]);
        if(!is_resource($process)) throw new RuntimeException('No se pudo iniciar la herramienta OCR.');
        $exit=-1;
        try {
            do {
                $state=proc_get_status($process);
                if(!$state['running']) {$exit=$state['exitcode'];break;}
                clearstatcache(true,$out);
                clearstatcache(true,$err);
                if(microtime(true)>=$deadline) {proc_terminate($process);throw new RuntimeException('Se agotó el tiempo máximo de lectura.');}
                if((is_file($out)&&filesize($out)>2*1024*1024) || (is_file($err)&&filesize($err)>2*1024*1024)) {proc_terminate($process);throw new RuntimeException('La salida del OCR superó el límite permitido.');}
                usleep(40000);
            } while(true);
        } finally {proc_close($process);}
        if($exit!==0) throw new RuntimeException('La herramienta terminó con un error. Revise su instalación y el idioma español.');
        return (string)file_get_contents($out);
    }
    private static function enhancedImage(string $path,string $destination): bool {
        if(!function_exists('imagecreatefromstring')) return false;
        $size=@getimagesize($path);
        if(!$size || $size[0]*$size[1]>25000000) return false;
        $image=@imagecreatefromstring(file_get_contents($path));
        if(!$image) return false;
        try {
            // Aplanar transparencias antes de convertir a grises: fondo blanco.
            $flat=imagecreatetruecolor(imagesx($image),imagesy($image));
            imagefill($flat,0,0,imagecolorallocate($flat,255,255,255));
            imagecopy($flat,$image,0,0,0,0,imagesx($image),imagesy($image));
            imagedestroy($image);$image=$flat;
            $cropped=imagecropauto($image,IMG_CROP_WHITE);
            if($cropped) {imagedestroy($image);$image=$cropped;}
            imagefilter($image,IMG_FILTER_GRAYSCALE);
            imagefilter($image,IMG_FILTER_CONTRAST,-20);
            // Corregir inclinaciones pequeñas con una proyección horizontal.
            // El muestreo acotado evita analizar millones de píxeles en PHP.
            $angle=self::skewAngle($image);
            if($angle!==0) {
                $rotated=imagerotate($image,$angle,imagecolorallocate($image,255,255,255));
                if($rotated) {imagedestroy($image);$image=$rotated;}
            }
            $scale=min(2,2400/max(imagesx($image),imagesy($image)));
            if($scale>1.1) {
                $scaled=imagescale($image,(int)round(imagesx($image)*$scale),(int)round(imagesy($image)*$scale),IMG_BICUBIC_FIXED);
                if($scaled) {imagedestroy($image);$image=$scaled;}
            }
            // Conservar un margen permite segmentar texto cercano al borde.
            $bordered=imagecreatetruecolor(imagesx($image)+40,imagesy($image)+40);
            imagefill($bordered,0,0,imagecolorallocate($bordered,255,255,255));
            imagecopy($bordered,$image,20,20,0,0,imagesx($image),imagesy($image));
            imagedestroy($image);$image=$bordered;
            return imagepng($image,$destination);
        } finally {imagedestroy($image);}
    }
    private static function skewAngle(GdImage $image): int {
        $scale=min(1,700/imagesx($image),700/imagesy($image));
        $width=max(1,(int)round(imagesx($image)*$scale));
        $sample=imagescale($image,$width,max(1,(int)round(imagesy($image)*$scale)));
        if(!$sample) return 0;
        $points=[];
        for($y=0;$y<imagesy($sample);$y+=3) for($x=0;$x<$width;$x+=3) {
            if((imagecolorat($sample,$x,$y)&255)<100) $points[]=[$x,$y];
        }
        imagedestroy($sample);
        if(count($points)<100) return 0;
        $scores=[];
        for($angle=-4;$angle<=4;$angle++) {
            $r=deg2rad($angle);$bins=[];
            foreach($points as [$x,$y]) {
                $bin=(int)round(($y*cos($r)-$x*sin($r))/3);
                $bins[$bin]=($bins[$bin]??0)+1;
            }
            $scores[$angle]=array_sum(array_map(fn($n)=>$n*$n,$bins));
        }
        $best=(int)array_search(max($scores),$scores,true);
        return $scores[$best]>$scores[0]*1.15?$best:0;
    }
    public static function readFiles(array $files,string $kind='sufragar'): array {
        // Windows cuenta también la espera del proceso externo: evitar el límite de 30 s.
        if(function_exists('set_time_limit'))set_time_limit(150);
        $result=['version'=>3,'kind'=>$kind,'status'=>'manual','checked_at'=>now(),'documents'=>[],'reasons'=>[],'diagnostics'=>[]];
        $dir=ROOT.'/storage/cache/ocr-'.bin2hex(random_bytes(12));
        if(!@mkdir($dir,0700,true)) {$result['reasons'][]='No se pudo preparar la lectura del soporte.';return $result;}
        $deadline=microtime(true)+110;$pages=0;
        try {
            foreach($files as $index=>$file) {
                $stage='preparación del archivo';
                try {
                    if($pages>=6) throw new RuntimeException('Límite de páginas OCR');
                    $path=$file['tmp']??attachmentPath($file);
                    $images=[$path];
                    if($file['mime']==='application/pdf') {
                        $stage='inspección del PDF';
                        $info=self::command([self::binary('pdfinfo'),$path],$dir,min($deadline,microtime(true)+5));
                        if(!preg_match('/^Pages:\s+(\d+)/m',$info,$m)) throw new RuntimeException('No se pudo contar el PDF');
                        if((int)$m[1]>3 || $pages+(int)$m[1]>6) throw new RuntimeException('El PDF supera el límite de lectura automática');
                        $prefix=$dir.'/page-'.$index;
                        $stage='conversión del PDF a imagen';
                        self::command([self::binary('pdftoppm'),'-r','300','-scale-to','3600','-f','1','-l',$m[1],'-png',$path,$prefix],$dir,min($deadline,microtime(true)+15));
                        $images=glob($prefix.'-*.png');
                        if(!$images) throw new RuntimeException('No se pudo convertir el PDF');
                    }
                    foreach($images as $image) {
                        $pages++;
                        $stage='lectura de texto de la página '.$pages;
                        $tsv=self::command([self::binary('tesseract'),$image,'stdout','-l','spa','--psm','3','tsv'],$dir,min($deadline,microtime(true)+15));
                        $parsed=self::fromTsv($tsv,$kind);
                        $passes=1;
                        if(!$parsed['recognized'] || count($parsed['documents'])!==1 || count($parsed['dates'])!==1) {
                            $prepared=$dir.'/enhanced-'.$pages.'.png';
                            $retryImage=self::enhancedImage($image,$prepared)?$prepared:$image;
                            try {
                                $retry=self::command([self::binary('tesseract'),$retryImage,'stdout','-l','spa','--psm','11','tsv'],$dir,min($deadline,microtime(true)+15));
                                $alternative=self::fromTsv($retry,$kind);$passes++;
                                $combinedStatus=self::combinedFieldStatus($parsed,$alternative);
                                $readFields=[];
                                foreach(['certificate_type','document_number','election_date'] as $field) $readFields[$field]=array_values(array_unique(array_merge($parsed['read_fields'][$field]??[],$alternative['read_fields'][$field]??[])));
                                // Solo combinar lecturas que reconocieron el tipo de documento.
                                if($alternative['recognized']) {
                                    $parsed=['recognized'=>true,
                                        'documents'=>array_values(array_unique(array_merge($parsed['documents'],$alternative['documents']))),
                                        'dates'=>array_values(array_unique(array_merge($parsed['dates'],$alternative['dates'])))];
                                }
                                $parsed['read_fields']=$readFields;
                                $parsed['field_status']=$combinedStatus;
                            } catch(RuntimeException $retryError) {
                                $result['reasons'][]='La segunda lectura de '.$file['original_name'].' no se completó. '.$retryError->getMessage();
                            }
                        }
                        if(!$parsed['recognized'] || count($parsed['documents'])!==1 || count($parsed['dates'])!==1) {
                            $lock=fopen(ROOT.'/storage/cache/ppocr.lock','c');
                            if($lock && flock($lock,LOCK_EX|LOCK_NB)) {
                                try {
                                    $models=$GLOBALS['config']['electoral_ocr']['models']??(PHP_OS_FAMILY==='Windows'?ROOT.'/.runtime/ocr/models':'/opt/ocr/models');
                                    $output=self::command([self::binary('python'),ROOT.'/scripts/ocr/read.py',$image,$models],$dir,$deadline);
                                    $pp=json_decode($output,true,512,JSON_THROW_ON_ERROR);
                                    if(($pp['unavailable']??'')==='memory') {
                                        $result['second_stage_unavailable']='memory';
                                        $result['reasons'][]='El alojamiento no dispone de memoria suficiente para la segunda etapa OCR. Revisión manual obligatoria.';
                                        throw new RuntimeException('Memoria insuficiente para PP-OCR');
                                    }
                                    $second=self::parse($pp['lines'],$kind,$pp['observed_lines']);
                                    $documents=array_values(array_unique(array_merge($parsed['documents'],$second['documents'])));
                                    $dates=array_values(array_unique(array_merge($parsed['dates'],$second['dates'])));
                                    $conflict=count($documents)>1 || count($dates)>1;
                                    $fieldStatus=self::combinedFieldStatus($parsed,$second);
                                    $conflict=$conflict || in_array('ambiguous',$fieldStatus,true);
                                    foreach(['certificate_type','document_number','election_date'] as $field) $parsed['read_fields'][$field]=array_values(array_unique(array_merge($parsed['read_fields'][$field]??[],$second['read_fields'][$field]??[])));
                                    $parsed['recognized']=$parsed['recognized'] || $second['recognized'];
                                    $parsed['documents']=$conflict?[]:$documents;$parsed['dates']=$conflict?[]:$dates;
                                    $parsed['second_stage']=$pp['engine'];
                                    $parsed['field_status']=$fieldStatus;
                                    if($conflict)$result['reasons'][]='Los motores OCR encontraron valores distintos. Revisión manual obligatoria.';
                                    $passes++;
                                } catch(Throwable $error) {
                                    $result['reasons'][]='La segunda etapa PP-OCR no pudo completarse; requiere revisión manual.';
                                } finally {flock($lock,LOCK_UN);fclose($lock);}
                            } else {
                                if($lock)fclose($lock);
                                $result['reasons'][]='La segunda etapa OCR está ocupada; requiere revisión manual.';
                            }
                        }
                        $result['documents'][]=$parsed+['sha256'=>$file['sha256'],'file'=>$file['original_name']];
                        $result['diagnostics'][]=['file'=>$file['original_name'],'page'=>$pages,'passes'=>$passes,'recognized'=>$parsed['recognized'],'document_candidates'=>count($parsed['documents']),'date_candidates'=>count($parsed['dates'])];
                    }
                } catch(Throwable $e) {
                    $detail=$e instanceof RuntimeException?$e->getMessage():'Error interno del procesamiento.';
                    $result['reasons'][]='No fue posible completar la lectura automática de '.$file['original_name'].' en la etapa de '.$stage.'. '.$detail;
                    $result['diagnostics'][]=['file'=>$file['original_name'],'stage'=>$stage,'error'=>$detail];
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
        if(($category['slug']??'')!=='sufragar' && !(($category['slug']??'')==='jurado' && ($input['jury_role']??'')==='jurado')) return null;
        if($id) {
            $old=row('SELECT user_id,status FROM requests WHERE id=?',[$id]);
            ensure($old && (int)$old['user_id']===(int)$owner['id'] && $old['status']==='returned','Solo puede corregir una solicitud propia devuelta.');
            $files=array_merge(rows('SELECT * FROM attachments WHERE request_id=?',[$id]),$files);
        }
        return self::readFiles($files,($category['slug']==='jurado'?'jurado':'sufragar'));
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
                $last=self::deadline($date,$report['kind']??'sufragar');
                foreach($slots as $slot) ensure($slot['date']>=$first && $slot['date']<=$last,'El compensatorio debe disfrutarse entre '.$first.' y '.$last.' ('.(($report['kind']??'')==='jurado'?'45 días hábiles':'30 días calendario').' siguientes a las elecciones).');
                if(count($document['documents'])===1 && $expected!==null) $valid[]=['election_date'=>$date,'document_number'=>$document['documents'][0],'sha256'=>$document['sha256']];
            }
        }
        $dates=array_unique(array_column($valid,'election_date'));
        if($valid && count($dates)===1 && !$report['reasons']) {
            $report['status']='verified';$report['election_date']=$valid[0]['election_date'];$report['document_number']=$valid[0]['document_number'];
            $report['valid_until']=self::deadline($report['election_date'],$report['kind']??'sufragar');
        } else {
            $report['status']='manual';
            $report['reasons'][]='No se identificaron inequívocamente el soporte electoral colombiano requerido, la cédula y una fecha de elecciones coincidente. Es obligatoria la revisión manual del soporte y del plazo de disfrute.';
        }
        return $report;
    }
    public static function deadline(string $date,string $kind): string {
        if($kind!=='jurado') return (new DateTimeImmutable($date))->modify('+30 days')->format('Y-m-d');
        $at=new DateTimeImmutable($date);$count=0;$holidays=array_flip(array_column(rows('SELECT date FROM holidays WHERE date>?',[$date]),'date'));
        while($count<45) { $at=$at->modify('+1 day'); if((int)$at->format('N')<=5 && !isset($holidays[$at->format('Y-m-d')])) $count++; }
        return $at->format('Y-m-d');
    }
    public static function report(array $request): ?array {
        $snapshot=json_decode($request['snapshot']??'{}',true);
        return is_array($snapshot['electoral_check']??null)?$snapshot['electoral_check']:null;
    }
    public static function needsManual(array $request): bool {
        return (self::report($request)['status']??'manual')!=='verified';
    }
    public static function combinedFieldStatus(array $first,array $second):array {
        $status=['certificate_type'=>!empty($first['recognized'])||!empty($second['recognized'])?'read':'missing'];
        foreach(['document_number'=>'documents','election_date'=>'dates'] as $field=>$key) {
            $values=array_unique(array_merge($first[$key]??[],$second[$key]??[]));
            $states=[$first['field_status'][$field]??'missing',$second['field_status'][$field]??'missing'];
            $status[$field]=count($values)>1||in_array('ambiguous',$states,true)?'ambiguous':(count($values)===1||in_array('read',$states,true)?'read':'missing');
        }
        return $status;
    }
    public static function manualReviewDetails(array $report):string {
        $labels=['certificate_type'=>'tipo de certificado','document_number'=>'cédula','election_date'=>'fecha de elecciones'];$details=[];
        $documents=$report['documents']??[];
        if(!$documents)return 'No fue posible leer con certeza el tipo de certificado, la cédula ni la fecha de elecciones.';
        foreach($documents as $index=>$document) {
            $fallback=['certificate_type'=>!empty($document['recognized'])?'read':'missing'];
            foreach(['document_number'=>'documents','election_date'=>'dates'] as $field=>$key) $fallback[$field]=count($document[$key]??[])===1?'read':(empty($document[$key])?'missing':'ambiguous');
            $states=($document['field_status']??[])+$fallback;$missing=[];$ambiguous=[];
            foreach($labels as $field=>$label) {
                if($states[$field]==='ambiguous')$ambiguous[]=$label;
                elseif($states[$field]!=='read')$missing[]=$label;
            }
            $prefix=count($documents)>1?'Soporte '.($index+1).': ':'';
            if($missing)$details[]=$prefix.'No fue posible leer con certeza: '.implode(', ',$missing).'.';
            if($ambiguous)$details[]=$prefix.'Lectura ambigua o contradictoria: '.implode(', ',$ambiguous).'.';
        }
        foreach(['documents'=>'cédula','dates'=>'fecha de elecciones'] as $key=>$label) {
            $values=[];foreach($documents as $document)$values=array_merge($values,$document[$key]??[]);
            if(count(array_unique($values))>1)$details[]='Revise '.$label.': hay valores distintos entre las lecturas de los soportes.';
        }
        return $details?implode(' ',array_unique($details)):'Los datos leídos no pudieron confirmarse conjuntamente. Revise el certificado completo y el plazo de disfrute.';
    }
    public static function summary(array $report): string {
        $resources=($report['second_stage_unavailable']??'')==='memory'?' La segunda etapa OCR no pudo ejecutarse por memoria insuficiente en el alojamiento.':'';
        return ($report['status']??'manual')==='verified'
            ?'Lectura OCR completada: cédula coincidente; elecciones '.$report['election_date'].'; disfrute permitido hasta '.$report['valid_until'].'. La lectura no certifica autenticidad.'
            :'REVISIÓN MANUAL OBLIGATORIA DEL CERTIFICADO ELECTORAL'.(($report['kind']??'')==='jurado'?' O CONSTANCIA DE JURADO':'').'. '.self::manualReviewDetails($report).$resources.' Compruebe los datos indicados contra el soporte original y verifique el plazo de disfrute.';
    }
}
