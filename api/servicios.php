<?php
// No requiere config.php - Funciona en localhost y en Railway

if (file_exists(__DIR__ . "/config.php")) {
    require_once __DIR__ . "/config.php";
}

// Fallback para Railway (variables de entorno) o simulación
if (!defined('RESEND_API_KEY')) {
    define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
}
if (!defined('EMAIL_AVISOS')) {
    define('EMAIL_AVISOS', getenv('EMAIL_AVISOS') ?: '');
}

// Avisa por email que un producto tiene stock bajo
function avisarStockBajo(array $producto): bool {
    $nombre = htmlspecialchars((string) ($producto['nombre'] ?? 'producto'));
    $cantidad = (int) ($producto['cantidad_stock'] ?? 0);
    $minimo = (int) ($producto['stock_minimo'] ?? 0);

    if (RESEND_API_KEY === "" || EMAIL_AVISOS === "") {
        error_log("[SIMULACIÓN] Stock bajo: $nombre (quedan $cantidad, mínimo $minimo)");
        return true;
    }
    if (!function_exists("curl_init")) return false;

    try {
        $ch = curl_init("https://api.resend.com/emails");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . RESEND_API_KEY,
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "from" => "onboarding@resend.dev",
                "to" => [EMAIL_AVISOS],
                "subject" => "Stock bajo: $nombre",
                "html" => "<p>El producto <strong>$nombre</strong> tiene stock bajo: quedan $cantidad unidades (mínimo: $minimo).</p>"
            ])
        ]);
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $respuesta !== false && $codigo >= 200 && $codigo < 300;
    } catch (Throwable $e) {
        error_log("Error aviso stock: " . $e->getMessage());
        return false;
    }
}

// Avisa por email que un producto está VENCIDO
function avisarVencido(array $producto): bool {
    $nombre = htmlspecialchars((string) ($producto['nombre'] ?? 'producto'));
    $vencimiento = (string) ($producto['fecha_vencimiento'] ?? '');

    if (RESEND_API_KEY === "" || EMAIL_AVISOS === "") {
        error_log("[SIMULACIÓN] Vencido: $nombre venció $vencimiento");
        return true;
    }
    if (!function_exists("curl_init")) return false;

    try {
        $ch = curl_init("https://api.resend.com/emails");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . RESEND_API_KEY,
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode([
                "from" => "onboarding@resend.dev",
                "to" => [EMAIL_AVISOS],
                "subject" => "Producto vencido: $nombre",
                "html" => "<p>El producto <strong>$nombre</strong> venció el $vencimiento.</p>"
            ])
        ]);
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $codigo >= 200 && $codigo < 300;
    } catch (Throwable $e) {
        error_log("Error aviso vencido: " . $e->getMessage());
        return false;
    }
}

// Cotización dólar oficial
function obtenerCotizacionDolar(): ?float {
    if (!function_exists("curl_init")) return null;
    try {
        $ch = curl_init("https://dolarapi.com/v1/dolares/oficial");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $respuesta = curl_exec($ch);
        curl_close($ch);
        $datos = json_decode($respuesta, true);
        $venta = $datos["venta"] ?? null;
        return is_numeric($venta) ? (float)$venta : null;
    } catch (Throwable $e) {
        return null;
    }
}
