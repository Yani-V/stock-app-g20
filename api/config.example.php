<?php
// ZAFE - config.example.php
// Instrucciones: Copiar este archivo como config.php y completar los datos
// Este archivo NO se sube a Railway. En Railway se usan Variables de entorno.

// Base de datos - localhost
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'zafe_db');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// Base de datos - Railway (ejemplo de lo que va en Variables de Railway)
// DB_HOST = mysql.railway.internal
// DB_NAME = railway
// DB_USER = root
// DB_PASSWORD = tu_pass_de_railway

// Servicio de email Resend (opcional, si no está entra en modo simulación)
define('RESEND_API_KEY', 're_...tu_clave...');
define('EMAIL_AVISOS', 'tu-email@gmail.com');
define('RESEND_FROM', 'onboarding@resend.dev');
define('RESEND_TO', 'tu-email@gmail.com');

// Entorno
define('ENTORNO', 'desarrollo');
