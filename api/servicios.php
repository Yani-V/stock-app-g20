<?php
require_once "config.php";

//Avisa por email (servicio Resend) que un producto tiene stock bajo.

function avisarStockBajo(array $producto): bool {
    $nombre = htmlspecialchars((string) ($producto['nombre'] ?? 'producto'));
    $cantidad = (int) ($producto['cantidad_stock'] ?? 0);
    $minimo = (int) ($producto['stock_minimo'] ?? 0);

    // Sin clave: modo simulación, no se envía nada, solo queda registrado en el log
    if (RESEND_API_KEY === "") {
        error_log("[SIMULACIÓN] Stock bajo: $nombre (quedan $cantidad, mínimo $minimo)");
        return true;
    }

    // Con clave pero sin destinatario configurado --> no hay a quién avisar
    if (EMAIL_AVISOS === "") {
        error_log("Aviso de stock bajo omitido: falta la variable EMAIL_AVISOS");
        return false;
    }

    if (!function_exists("curl_init")) {
        error_log("cURL no está disponible en este servidor");
        return false;
    }

    try {
        $ch = curl_init("https://api.resend.com/emails");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 5,       // máximo de 5s de espera para un servicio caído no cuelgue la API
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . RESEND_API_KEY,
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode([
                // Remitente de prueba de Resend
                "from" => "onboarding@resend.dev",
                "to" => [EMAIL_AVISOS],
                "subject" => "Stock bajo: $nombre",
                "html" => "<p>El producto <strong>$nombre</strong> tiene stock bajo: quedan $cantidad unidades (mínimo: $minimo).</p>"
            ])
        ]);

        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        // Falla de conexión (sin internet, dominio inalcanzable o timeout)
        if ($respuesta === false) {
            error_log("Falló la conexión con Resend: $errorCurl");
            return false;
        }

        // Resend respondió pero rechazó el pedido
        if ($codigo < 200 || $codigo >= 300) {
            error_log("Resend respondió con código $codigo");
            return false;
        }
        return true;
    } catch (Throwable $e) {
        // Línea de seguridad para que el guardado del producto no se rompa ante un error inesperado
        error_log("Error inesperado en el aviso por email: " . $e->getMessage());
        return false;
    }
}

// Avisa por email que un producto está VENCIDO
function avisarVencido(array $producto): bool {
    $nombre = htmlspecialchars((string) ($producto['nombre'] ?? 'producto'));
    $vencimiento = (string) ($producto['fecha_vencimiento'] ?? '');

    if (RESEND_API_KEY === "" || EMAIL_AVISOS === "") return false;
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
                "html" => "<p>El producto <strong>$nombre</strong> venció el $vencimiento y debe retirarse.</p>"
            ])
        ]);
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $codigo >= 200 && $codigo < 300;
    } catch (Throwable $e) {
        error_log("Error en aviso vencido: " . $e->getMessage());
        return false;
    }
}


// Consulta la cotización del dólar oficial usando DolarApi

function obtenerCotizacionDolar(): ?float {
    if (!function_exists("curl_init")) {
        error_log("cURL no está disponible en este servidor");
        return null;
    }

    try {
        $ch = curl_init("https://dolarapi.com/v1/dolares/oficial");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5    // un servicio caído de colgará la API
        ]);

        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            error_log("Falló la conexión con DolarApi: $errorCurl");
            return null;
        }

        // Se valida el formato por si la API cambia
        $datos = json_decode($respuesta, true);
        $venta = $datos["venta"] ?? null;
        if(!is_numeric($venta) || $venta <= 0) {
            error_log("DolarApi devolvió una respuesta inesperada");
            return null;
        }
        return (float) $venta;
    } catch (Throwable $e) {
        error_log("Error inesperado al consultar el dólar: ". $e->getMessage());
        return null;
    }
}