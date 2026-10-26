CREATE DATABASE IF NOT EXISTS antigua_viva_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE antigua_viva_db;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    rol ENUM('Administrador', 'Agente de ventas') NOT NULL,
    activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- Tabla de Tours
CREATE TABLE IF NOT EXISTS tours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio_persona DECIMAL(10,2) NOT NULL,
    cupo_maximo INT NOT NULL,
    duracion_horas INT NOT NULL,
    activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- Tabla de Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    nacionalidad VARCHAR(50) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(100)
) ENGINE=InnoDB;

-- Tabla Principal: Reservas
CREATE TABLE IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tour_id INT NOT NULL,
    cliente_id INT NOT NULL,
    fecha_tour DATE NOT NULL,
    cantidad_personas INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    estado ENUM('confirmada', 'cancelada') DEFAULT 'confirmada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Datos iniciales de prueba
INSERT IGNORE INTO tours (id, nombre, descripcion, precio_persona, cupo_maximo, duracion_horas, activo) VALUES
(1, 'Caminata Volcán Pacaya', 'Ascenso al volcán activo con tour guiado.', 150.00, 15, 6, 1),
(2, 'Tour Finca de Café', 'Recorrido interactivo de siembra y degustación.', 200.00, 10, 4, 1),
(3, 'Recorrido Colonial Antigua', 'Visita por las iglesias y calles empedradas.', 100.00, 20, 3, 1);

INSERT IGNORE INTO clientes (id, nombre, nacionalidad, telefono, correo) VALUES
(1, 'Allan López', 'Guatemalteca', '55551234', 'allan@example.com');