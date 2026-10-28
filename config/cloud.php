<?php
declare(strict_types=1);
// Se carga solo cuando APP_CLOUD=1. Los secretos se configuran en el alojamiento.
$required=['APP_MYSQL_HOST','APP_MYSQL_DATABASE','APP_MYSQL_USERNAME','APP_MYSQL_PASSWORD','APP_MYSQL_CA_BASE64','APP_BASE_URL'];
foreach ($required as $name) if (!getenv($name)) throw new RuntimeException('Falta configurar una variable de despliegue: '.$name);
$ca=base64_decode(getenv('APP_MYSQL_CA_BASE64'),true);
if ($ca===false || !str_contains($ca,'-----BEGIN CERTIFICATE-----')) throw new RuntimeException('Certificado CA MySQL no válido.');
$certificate=dirname(__DIR__).'/storage/mysql-ca.pem';
if (!is_dir(dirname($certificate))) mkdir(dirname($certificate),0700,true);
if (!is_file($certificate) || file_get_contents($certificate)!==$ca) {
    if (file_put_contents($certificate,$ca,LOCK_EX)===false) throw new RuntimeException('No se pudo guardar la CA MySQL.');
    chmod($certificate,0600);
}
return [
    'mysql'=>['host'=>getenv('APP_MYSQL_HOST'),'port'=>(int)(getenv('APP_MYSQL_PORT')?:3306),'database'=>getenv('APP_MYSQL_DATABASE'),'username'=>getenv('APP_MYSQL_USERNAME'),'password'=>getenv('APP_MYSQL_PASSWORD'),'ssl_ca'=>$certificate],
    'base_url'=>rtrim(getenv('APP_BASE_URL'),'/'),
    'attachment_storage'=>'mysql',
    'trust_proxy'=>true,
];
