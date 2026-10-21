CREATE DATABASE IF NOT EXISTS gestor_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestor_stock;

CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    codigo_barras VARCHAR(200) UNIQUE NOT NULL,
    cantidad_stock INT UNSIGNED NOT NULL,
    stock_minimo INT UNSIGNED NOT NULL,
    fecha_ingreso DATETIME NOT NULL, -- ENVIARLA POR PHP EN EL POST, NO SE HACE AUTOMATICAMENTE
    fecha_vencimiento DATE, -- NO SE EXIGE PARA POSIBLES PRODUCTOS SIN VENCIMIENTO
    proveedor VARCHAR(200) NOT NULL,
    ubicacion VARCHAR(200) NOT NULL, 
    precio_unitario DECIMAL(10, 2) NOT NULL
);