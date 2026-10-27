<?php
return [
    'mysql' => [
        'host' => '127.0.0.1', 'port' => 3310, 'database' => 'permisos_2026',
        'username' => 'permisos_app', 'password' => '',
    ],
    'uploads' => dirname(__DIR__) . '/storage/uploads',
    'base_url' => 'http://127.0.0.1:8088',
    'smtp' => [
        // Sin servidor configurado, los mensajes permanecen pendientes en la cola.
        'host' => '', 'port' => 587, 'username' => '', 'password' => '',
        'encryption' => 'tls', 'from' => '', 'from_name' => 'Talento Humano · Bucaramanga',
    ],
];
