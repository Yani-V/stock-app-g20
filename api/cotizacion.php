<?php
require_once "servicios.php";

// CORS solo para desarrollo con Live Server (mismo criterio que productos.php)
$origen = $_SERVER["HTTP_ORIGIN"] ?? "";
$origenesPermitidos = ["http://127.0.0.1:5500", "http://localhost:5500"];

if(in_array($origen, $origenesPermitidos, true)) {
    header("Access-Control-Allow-Origin: $origen");
    header("Vary: Origin");
}
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"], JSON_UNESCAPED_UNICODE);
    exit();
}

$cotizacion = obtenerCotizacionDolar();

if($cotizacion === null) {
    http_response_code(503); // la dependencia externa no está disponible
    echo json_encode(["error" => "Cotización no disponible"], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code(200);
echo json_encode(["cotizacion" => $cotizacion]);