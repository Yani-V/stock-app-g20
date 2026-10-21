<?php
// ZAFE - config.example.php
// Instrucciones: copiar como config.php y completar con datos de Railway
$_ENV['DB_HOST'] = getenv('DB_HOST') ?: 'localhost';
$_ENV['DB_PORT'] = getenv('DB_PORT') ?: '3306';
$_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'zafe_db';
$_ENV['DB_USER'] = getenv('DB_USER') ?: 'root';
$_ENV['DB_PASS'] = getenv('DB_PASS') ?: '';
$_ENV['RESEND_API_KEY'] = getenv('RESEND_API_KEY') ?: 're_...';
$_ENV['EMAIL_AVISOS'] = getenv('EMAIL_AVISOS') ?: 'tu-email-de-prueba@gmail.com';
$_ENV['ENTORNO'] = getenv('ENTORNO') ?: 'desarrollo';