<?php

require "conexion.php";
require "servicios.php";
require "validaciones.php";


// SOLO PARA CUANDO SE USA XAMPP
$origen = $_SERVER["HTTP_ORIGIN"] ?? ""; 

$origenesPermitidos = [
    "http://127.0.0.1:5500",
    "http://localhost:5500"
];
// Verifica que $origen se encuentre contenido dentro de $origenesPermitidos
if (in_array($origen, $origenesPermitidos, true)) {
    header("Access-Control-Allow-Origin: $origen");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

header("Content-Type: application/json; charset=utf-8");

if($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$metodo = $_SERVER["REQUEST_METHOD"];


if($metodo === "POST") {
    $cuerpo = json_decode(file_get_contents("php://input"), true);
    validarProducto($cuerpo);
    
    $fecha_ingreso = date("Y-m-d H:i:s");

    $fecha_vencimiento = null; // por defecto: sin vencimiento

    // verifica si llega alguna fecha de vencimiento
    if(isset($cuerpo["fecha_vencimiento"]) && $cuerpo["fecha_vencimiento"] !== ""){
        $fecha_vencimiento = $cuerpo["fecha_vencimiento"];
    }
    
    $consulta = $conexion->prepare(
    "INSERT INTO productos (nombre, codigo_barras, cantidad_stock, stock_minimo, fecha_ingreso, fecha_vencimiento, proveedor, ubicacion, precio_unitario)
     VALUES (:nombre, :codigo, :stock, :minimo, :ingreso, :vencimiento, :proveedor, :ubicacion, :precio)"
    );

    try{
        $consulta->execute([
            ":nombre"    => $cuerpo["nombre"],
            ":codigo"    => $cuerpo["codigo_barras"],
            ":stock"     => $cuerpo["cantidad_stock"],
            ":minimo"    => $cuerpo["stock_minimo"],
            ":ingreso"   => $fecha_ingreso,
            ":vencimiento" => $fecha_vencimiento,
            ":proveedor" => $cuerpo["proveedor"],
            ":ubicacion" => $cuerpo["ubicacion"],
            ":precio"    => $cuerpo["precio_unitario"],
        ]);

        $producto = [
            "id"       => (int) $conexion->lastInsertId(),
            "nombre"   => $cuerpo["nombre"],
            "codigo_barras"    => $cuerpo["codigo_barras"],
            "cantidad_stock"   => $cuerpo["cantidad_stock"],
            "stock_minimo"  => $cuerpo["stock_minimo"],
            "fecha_ingreso" => $fecha_ingreso,
            "fecha_vencimiento" => $fecha_vencimiento,
            "proveedor" => $cuerpo["proveedor"],
            "ubicacion" => $cuerpo["ubicacion"],
            "precio_unitario" => $cuerpo["precio_unitario"]
        ];

        // Aviso de stock bajo
        if ((int) $cuerpo["cantidad_stock"] <= (int) $cuerpo["stock_minimo"]) {
            avisarStockBajo($producto);
        }

        http_response_code(201);
        echo json_encode($producto, JSON_UNESCAPED_UNICODE);
        exit();
    }
    catch(PDOException $e){
        if($e->errorInfo[1] === 1062){ // 1062 = codigo de entrada duplicado // verifica código de barras duplicado(funciona porque ese campo tiene UNIQUE)
            http_response_code(409);
            echo json_encode(["error" => "Ya existe un producto con ese código de barras"], JSON_UNESCAPED_UNICODE);
        } 
        else{
            error_log("Error al crear producto: " . $e->getMessage()); //guarda el detalle del error en el log del servidor. No puede acceder el usuario
            http_response_code(500);
            echo json_encode(["error" => "No se pudo crear el producto"], JSON_UNESCAPED_UNICODE);
        }
        exit();
    }
    
}

if($metodo === "PUT") {
    if (!isset($_GET["id"])) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el id"]);
        exit();
    }

    $cuerpo = json_decode(file_get_contents("php://input"), true);
    verificarProductoExiste($conexion, $_GET["id"]);
    validarProducto($cuerpo);

    $fecha_vencimiento = null; // por defecto: sin vencimiento

    // verifica si llega alguna fecha de vencimiento
    if(isset($cuerpo["fecha_vencimiento"]) && $cuerpo["fecha_vencimiento"] !== ""){
        $fecha_vencimiento = $cuerpo["fecha_vencimiento"];
    }


    $consulta = $conexion->prepare(
        "UPDATE productos SET nombre = :nombre, codigo_barras = :codigo, cantidad_stock = :stock,
        stock_minimo = :minimo, fecha_vencimiento = :vencimiento, proveedor = :proveedor,
        ubicacion = :ubicacion, precio_unitario = :precio
        WHERE id = :id"
    );
    try{
        $consulta->execute([
            ":nombre" => $cuerpo["nombre"],
            ":codigo" => $cuerpo["codigo_barras"],
            ":stock" => $cuerpo["cantidad_stock"],
            ":minimo" => $cuerpo["stock_minimo"],
            ":vencimiento" => $fecha_vencimiento,
            ":proveedor" => $cuerpo["proveedor"],
            ":ubicacion" => $cuerpo["ubicacion"],
            ":precio" => $cuerpo["precio_unitario"],
            ":id" => $_GET["id"],
        ]);

        $producto = [
            "id" => (int)$_GET["id"],
            "nombre" => $cuerpo["nombre"],
            "codigo_barras" => $cuerpo["codigo_barras"],
            "cantidad_stock" => $cuerpo["cantidad_stock"],
            "stock_minimo" => $cuerpo["stock_minimo"],
            "fecha_vencimiento" => $fecha_vencimiento,
            "proveedor" => $cuerpo["proveedor"],
            "ubicacion" => $cuerpo["ubicacion"],
            "precio_unitario" => $cuerpo["precio_unitario"]
        ];

         // Aviso de stock bajo
        if ((int) $cuerpo["cantidad_stock"] <= (int) $cuerpo["stock_minimo"]) {
            avisarStockBajo($producto);
        }

        // Aviso de producto vencido
        if ($fecha_vencimiento !== null && strtotime($fecha_vencimiento) <strtotime(date("Y-m-d"))) {
            avisarVencido($producto);
        }

        http_response_code(200);
        echo json_encode($producto, JSON_UNESCAPED_UNICODE);
        exit();
    }
    catch(PDOException $e){
        if($e->errorInfo[1] === 1062){ // 1062 = codigo de entrada duplicado // verifica código de barras duplicado(funciona porque ese campo tiene UNIQUE)
            http_response_code(409);
            echo json_encode(["error" => "Ya existe un producto con ese código de barras"], JSON_UNESCAPED_UNICODE);
        } 
        else{
            error_log("Error al editar producto: " . $e->getMessage()); //guarda el detalle del error en el log del servidor. No puede acceder el usuario
            http_response_code(500);
            echo json_encode(["error" => "No se pudo editar el producto"], JSON_UNESCAPED_UNICODE);
        }
        exit();
    }
}

if($metodo === "DELETE") {
    if (!isset($_GET["id"])) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el id"]);
        exit();
    }

    verificarProductoExiste($conexion, $_GET["id"]);

    $consulta = $conexion->prepare("DELETE FROM productos WHERE id = :id");

    try{
        $consulta->execute([":id" => $_GET["id"]]);
        http_response_code(204);
        exit();
    }
    catch(PDOException $e){
        http_response_code(500);
        error_log("Error al eliminar producto: " . $e->getMessage());
        echo json_encode(["error" => "No se pudo eliminar el producto"]);
        exit();
    } 
}

// GET con id
if(isset($_GET["id"])) {
    verificarProductoExiste($conexion, $_GET["id"]);

    $consulta = $conexion->prepare("SELECT * FROM productos WHERE id = :id");
    try{
        $consulta->execute([":id" => $_GET["id"]]);
        $producto = $consulta->fetch(PDO::FETCH_ASSOC);
        echo json_encode($producto, JSON_UNESCAPED_UNICODE);
        exit();
    }
    catch(PDOException $e){
        http_response_code(500);
        error_log("Error al obtener producto: " . $e->getMessage());
        echo json_encode(["error" => "No se pudo obtener el producto"]);
        exit();
    } 
}

// GET por omisión
$consulta = $conexion->query("SELECT * FROM productos ORDER BY nombre");
echo json_encode($consulta->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);

