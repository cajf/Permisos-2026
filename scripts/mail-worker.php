<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require __DIR__.'/../app/bootstrap.php';
$smtp=$GLOBALS['config']['smtp'];
if (!$smtp['host'] || !$smtp['from']) { echo "SMTP no configurado. La cola permanece pendiente.\n"; exit(0); }
foreach (rows("SELECT * FROM outbox WHERE status IN ('pending','failed') AND attempts<5 ORDER BY id LIMIT 50") as $item) {
    // Reclamo atómico: evita que dos trabajadores envíen el mismo mensaje.
    if (!run("UPDATE outbox SET status='sending',attempts=attempts+1 WHERE id=? AND status IN ('pending','failed')",[$item['id']])) continue;
    try {
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host=$smtp['host']; $mail->Port=(int)$smtp['port'];
        $mail->SMTPAuth=$smtp['username']!==''; $mail->Username=$smtp['username']; $mail->Password=$smtp['password'];
        $mail->SMTPSecure=$smtp['encryption']; $mail->CharSet='UTF-8'; $mail->Timeout=15;
        $mail->setFrom($smtp['from'],$smtp['from_name']); $mail->addAddress($item['recipient']);
        $mail->Subject=$item['subject']; $mail->Body=$item['body']."\n\n".$GLOBALS['config']['base_url'];
        $mail->send(); run("UPDATE outbox SET status='sent',sent_at=?,last_error=NULL WHERE id=?",[now(),$item['id']]);
    } catch (Throwable $ex) { run("UPDATE outbox SET status='failed',last_error=? WHERE id=?",[mb_substr($ex->getMessage(),0,1000),$item['id']]); }
}
echo "Lote de correo procesado.\n";
