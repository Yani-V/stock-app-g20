<?php
    function validarProducto(&$cuerpo){ // Se usa & para que la variable sea por referencia, de esta manera al hacer trim modifica el $cuerpo original
        if(!is_array($cuerpo)){      // Verifica si el json_decode devuelve vacio o null
            http_response_code(400);
            echo json_encode(["error" => "El cuerpo debe ser un JSON válido"], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // VALIDACION DE CAMPOS VACIOS
        $obligatorios = ["nombre", "codigo_barras", "cantidad_stock", "stock_minimo", "proveedor", "ubicacion", "precio_unitario"];
        $campos_faltantes = [];

        foreach($obligatorios as $campo){
            if(!isset($cuerpo[$campo]) || $cuerpo[$campo] === ""){
                $campos_faltantes[] = str_replace("_", " ",$campo); // se remplaza el"_" con " " para que no se envie el nombre literal de las columnas de la db
            }
        }

        if(!empty($campos_faltantes)){
            http_response_code(400);
            echo json_encode(["error" => "Faltan: " . implode(", ", $campos_faltantes)], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // VALIDACION DE TIPOS DE DATO

        // validacion de str
        $campos_str = ["nombre", "codigo_barras", "proveedor", "ubicacion"]; 
        foreach($campos_str as $campo){
            $cuerpo[$campo] = is_string($cuerpo[$campo]) ? trim($cuerpo[$campo]) : "";

            if($cuerpo[$campo] === "" || mb_strlen($cuerpo[$campo]) > 200){
                http_response_code(422);
                echo json_encode(["error" => "Nombre, código de barras, proveedor y ubicación deben ser texto de 1 a 200 caracteres"], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        // validacion de int
        if(!is_int($cuerpo["cantidad_stock"]) || $cuerpo["cantidad_stock"] < 0){
            http_response_code(422);
            echo json_encode(["error" => "La cantidad en stock debe ser un entero mayor o igual a 0"], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if(!is_int($cuerpo["stock_minimo"]) || $cuerpo["stock_minimo"] < 0){
            http_response_code(422);
            echo json_encode(["error" => "El stock mínimo debe ser un entero mayor o igual a 0"], JSON_UNESCAPED_UNICODE);
            exit();
        }

        //validacion de decimal o numeric
        if(!is_numeric($cuerpo["precio_unitario"]) || $cuerpo["precio_unitario"] < 0 || $cuerpo["precio_unitario"] > 99999999.99){
            http_response_code(422);
            echo json_encode(["error" => "El precio unitario debe ser un número mayor o igual a 0 y menor a 99999999.99"], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }

// Si no existe un producto con ese id, responde 404 y corta la ejecución.
function verificarProductoExiste($conexion, $id) {
    $consulta = $conexion->prepare("SELECT id FROM productos WHERE id = :id");
    $consulta->execute([":id" => $id]);

    if ($consulta->fetch() === false) {
        http_response_code(404);
        echo json_encode(["error" => "No existe un producto con ese id"], JSON_UNESCAPED_UNICODE);
        exit();
    }
}