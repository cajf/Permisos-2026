<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (in_array($path,['/assets/app.css','/assets/app.js','/assets/alcaldia-bucaramanga.png'],true)) return false;
require __DIR__.'/index.php';
