<?php
// No usamos config.php porque no se sube a GitHub por consigna.
// Leemos directo de las Variables de Entorno de Railway

if (getenv('ENTORNO') === 'produccion' || getenv('MYSQLHOST')) {
    ini_set("display_errors", "0");
    ini_set("log_errors", "1");
}

// Si las variables vienen de Railway (MYSQLHOST) o de tu config local
$host = getenv('MYSQLHOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
$port = getenv('MYSQLPORT') ?: (defined('DB_PORT') ? DB_PORT : '3306');
$dbname = getenv('MYSQL_DATABASE') ?: getenv('MYSQLDATABASE') ?: (defined('DB_NAME') ? DB_NAME : 'railway');
$user = getenv('MYSQLUSER') ?: (defined('DB_USER') ? DB_USER : 'root');
$pass = getenv('MYSQLPASSWORD') ?: (defined('DB_PASS') ? DB_PASS : '');

try {
    $conexion = new PDO(
        "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $dbname,
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log("Conexión fallida: " . $e->getMessage());
    // No mostramos el error al usuario en producción
    die("Error de conexión a la base de datos");
}
