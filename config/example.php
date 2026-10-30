<?php
return [
    'mysql' => [
        'host' => '127.0.0.1', 'port' => 3310, 'database' => 'permisos_2026',
        'username' => 'permisos_app', 'password' => '',
        'ssl_ca' => '',
    ],
    'uploads' => dirname(__DIR__) . '/storage/uploads',
    'base_url' => 'http://127.0.0.1:8088',
    'attachment_storage' => 'filesystem',
    'trust_proxy' => false,
    // Rutas absolutas opcionales. Vacías: .runtime en Windows y PATH en Linux.
    'electoral_ocr' => ['tesseract'=>'', 'pdftoppm'=>'', 'pdfinfo'=>''],
    'mail'=>[
        'enabled'=>getenv('APP_MAIL_ENABLED')==='1',
        'api_key'=>getenv('APP_BREVO_API_KEY')?:'',
        'from'=>getenv('APP_MAIL_FROM')?:'',
        'from_name'=>getenv('APP_MAIL_FROM_NAME')?:'Notificaciones de prueba · Permisos Bucaramanga',
        'start_at'=>getenv('APP_MAIL_START_AT')?:'',
        'daily_limit'=>(int)(getenv('APP_MAIL_DAILY_LIMIT')?:250),
    ],
    'smtp' => [
        // Sin servidor configurado, los mensajes permanecen pendientes en la cola.
        'host' => '', 'port' => 587, 'username' => '', 'password' => '',
        'encryption' => 'tls', 'from' => '', 'from_name' => 'Talento Humano · Bucaramanga',
    ],
];
