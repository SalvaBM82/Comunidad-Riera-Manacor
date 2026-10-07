CREATE DATABASE IF NOT EXISTS if0_43039922_comunidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE if0_43039922_comunidad;

CREATE TABLE unidades (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 propietario VARCHAR(150) NOT NULL,
 email VARCHAR(190),
 telefono VARCHAR(50),
 coef_general DECIMAL(6,3) NOT NULL,
 coef_escalera DECIMAL(6,3) NOT NULL DEFAULT 0,
 tiene_acceso_escalera TINYINT(1) NOT NULL DEFAULT 0,
 m2 DECIMAL(10,2),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO unidades (nombre, propietario, coef_general, coef_escalera, tiene_acceso_escalera) VALUES
('Local Comercial','Propietario Local',20,0,0),
('Garaje Privado','Propietario Garaje',20,0,0),
('Piso 1º','Propietario Piso 1º',40,50,1),
('Piso 2º','Propietario Piso 2º',20,50,1);

CREATE TABLE usuarios (
 id INT AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) UNIQUE NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 unidad_id INT NULL,
 rol ENUM('PRESIDENTE','PROPIETARIO') NOT NULL DEFAULT 'PROPIETARIO',
 activo TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE SET NULL
);

-- Usuario inicial: admin@comunidad.local / Cambiar123!
INSERT INTO usuarios (email,password_hash,unidad_id,rol)
VALUES ('admin@comunidad.local', '$2y$10$92IXUN6YQJ9f8qXK8w7KQe7x1Jr6mX7Gf4g2M0p0fM4X5gK2Xj3i2', 3, 'PRESIDENTE');

CREATE TABLE gastos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 fecha DATE NOT NULL,
 concepto VARCHAR(255) NOT NULL,
 proveedor VARCHAR(190),
 importe_total DECIMAL(12,2) NOT NULL,
 tipo_gasto ENUM('GENERAL','ESCALERA') NOT NULL,
 factura_url VARCHAR(500),
 pagado TINYINT(1) NOT NULL DEFAULT 0,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE presupuestos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 anio YEAR NOT NULL,
 tipo_gasto ENUM('GENERAL','ESCALERA') NOT NULL,
 concepto VARCHAR(255) NOT NULL,
 importe_previsto DECIMAL(12,2) NOT NULL DEFAULT 0,
 importe_real DECIMAL(12,2) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE recibos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 anio YEAR NOT NULL,
 mes TINYINT NOT NULL,
 unidad_id INT NOT NULL,
 importe_general DECIMAL(12,2) NOT NULL DEFAULT 0,
 importe_escalera DECIMAL(12,2) NOT NULL DEFAULT 0,
 total DECIMAL(12,2) NOT NULL DEFAULT 0,
 estado ENUM('PENDIENTE','PAGADO','VENCIDO') NOT NULL DEFAULT 'PENDIENTE',
 fecha_vencimiento DATE NULL,
 fecha_pago DATE NULL,
 UNIQUE KEY uq_recibo (anio,mes,unidad_id),
 FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
);

CREATE TABLE derramas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(255) NOT NULL,
 descripcion TEXT,
 fecha_acuerdo_junta DATE,
 importe_total DECIMAL(12,2) NOT NULL,
 tipo ENUM('GENERAL','ESCALERA') NOT NULL,
 fecha_limite DATE NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE adelantos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 derrama_id INT NULL,
 recibo_id INT NULL,
 deudor_id INT NOT NULL,
 acreedor_id INT NOT NULL,
 importe DECIMAL(12,2) NOT NULL,
 fecha DATE NOT NULL,
 concepto VARCHAR(255) NOT NULL,
 FOREIGN KEY (derrama_id) REFERENCES derramas(id) ON DELETE SET NULL,
 FOREIGN KEY (recibo_id) REFERENCES recibos(id) ON DELETE SET NULL,
 FOREIGN KEY (deudor_id) REFERENCES unidades(id) ON DELETE CASCADE,
 FOREIGN KEY (acreedor_id) REFERENCES unidades(id) ON DELETE CASCADE
);

CREATE TABLE incidencias (
 id INT AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(255) NOT NULL,
 descripcion TEXT,
 tipo ENUM('GENERAL','ESCALERA') NOT NULL,
 estado ENUM('ABIERTA','EN_CURSO','RESUELTA') NOT NULL DEFAULT 'ABIERTA',
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE documentos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(255) NOT NULL,
 categoria VARCHAR(100),
 archivo_url VARCHAR(500) NOT NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE logs (
 id INT AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT NULL,
 accion VARCHAR(255) NOT NULL,
 entidad_afectada VARCHAR(100),
 fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE intereses_legales (
 id INT AUTO_INCREMENT PRIMARY KEY,
 desde DATE NOT NULL,
 hasta DATE NULL,
 porcentaje DECIMAL(8,4) NOT NULL
);

CREATE TABLE votaciones (
 id INT AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(255) NOT NULL,
 descripcion TEXT,
 tipo ENUM('GENERAL','ESCALERA') NOT NULL,
 fecha_inicio DATETIME NOT NULL,
 fecha_fin DATETIME NOT NULL,
 estado ENUM('ABIERTA','CERRADA') NOT NULL DEFAULT 'ABIERTA'
);

CREATE TABLE votos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 votacion_id INT NOT NULL,
 unidad_id INT NOT NULL,
 voto ENUM('SI','NO','ABSTENCION') NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_voto (votacion_id,unidad_id),
 FOREIGN KEY (votacion_id) REFERENCES votaciones(id) ON DELETE CASCADE,
 FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
);
