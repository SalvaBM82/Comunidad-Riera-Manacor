CREATE DATABASE IF NOT EXISTS if0_43039922_comunidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE if0_43039922_comunidad;

CREATE TABLE roles (
 id INT AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(50) NOT NULL UNIQUE,
 nombre VARCHAR(100) NOT NULL,
 descripcion VARCHAR(255),
 sistema TINYINT(1) NOT NULL DEFAULT 0,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (codigo,nombre,descripcion,sistema) VALUES
('PRESIDENTE','Presidente','Cargo de presidencia de la comunidad. Puede acumularse con otros roles.',1),
('VICEPRESIDENTE','Vicepresidente','Sustituye al Presidente cuando corresponda.',1),
('ADMINISTRADOR','Administrador','Gestiona la administración ordinaria de la comunidad.',1),
('SECRETARIO','Secretario','Gestiona documentación, actas y tareas de secretaría.',1),
('TESORERO','Tesorero','Gestiona la parte económica y de cobros de la comunidad.',1),
('VOCAL','Vocal','Participa en la gestión y seguimiento de asuntos de la comunidad.',1),
('PROPIETARIO','Propietario','Acceso básico como propietario de una unidad.',1),
('PROPIETARIO_ESCALERA','Propietario con escalera','Propietario cuya unidad participa en gastos de escalera.',1),
('PROPIETARIO_SIN_ESCALERA','Propietario sin escalera','Propietario cuya unidad no participa en gastos de escalera.',1);

CREATE TABLE permisos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(80) NOT NULL UNIQUE,
 nombre VARCHAR(120) NOT NULL,
 descripcion VARCHAR(255)
);

INSERT INTO permisos (codigo,nombre,descripcion) VALUES
('GESTION_USUARIOS','Gestionar usuarios','Crear, editar, activar y desactivar usuarios.'),
('GESTION_ROLES','Gestionar roles','Crear, editar y activar/desactivar roles y sus permisos.'),
('CAMBIO_PROPIETARIO','Cambiar propietario','Registrar cambios de titularidad y consultar el histórico.'),
('GESTION_UNIDADES','Gestionar unidades','Consultar y administrar unidades y coeficientes.'),
('GESTION_GASTOS','Gestionar gastos','Crear y administrar gastos.'),
('GESTION_PRESUPUESTOS','Gestionar presupuestos','Crear y administrar presupuestos.'),
('GESTION_RECIBOS','Gestionar recibos','Generar y administrar recibos.'),
('GESTION_DERRAMAS','Gestionar derramas','Crear y administrar derramas.'),
('VER_MOROSIDAD','Ver morosidad','Consultar el informe de morosidad.'),
('GESTION_INCIDENCIAS','Gestionar incidencias','Crear y administrar incidencias.'),
('GESTION_DOCUMENTOS','Gestionar documentos','Administrar documentos de la comunidad.'),
('GESTION_VOTACIONES','Gestionar votaciones','Administrar votaciones y votos.'),
('GESTION_BACKUPS','Gestionar copias de seguridad','Crear y restaurar copias completas de la base de datos.');

CREATE TABLE rol_permisos (
 rol_id INT NOT NULL,
 permiso_id INT NOT NULL,
 PRIMARY KEY (rol_id,permiso_id),
 FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
 FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
);

-- Administrador: acceso completo a la aplicación, incluida su administración técnica.
INSERT INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p WHERE r.codigo='ADMINISTRADOR';

-- Presidente: permisos de gestión de la comunidad, pero no administración técnica.
INSERT INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p
  ON p.codigo IN ('CAMBIO_PROPIETARIO','GESTION_UNIDADES','GESTION_GASTOS','GESTION_PRESUPUESTOS','GESTION_RECIBOS','GESTION_DERRAMAS','VER_MOROSIDAD','GESTION_INCIDENCIAS','GESTION_DOCUMENTOS','GESTION_VOTACIONES')
WHERE r.codigo='PRESIDENTE';

INSERT INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('GESTION_DOCUMENTOS','GESTION_INCIDENCIAS','GESTION_VOTACIONES') WHERE r.codigo IN ('SECRETARIO','VICEPRESIDENTE','VOCAL');

INSERT INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo IN ('GESTION_GASTOS','GESTION_PRESUPUESTOS','GESTION_RECIBOS','GESTION_DERRAMAS','VER_MOROSIDAD') WHERE r.codigo='TESORERO';

INSERT INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='GESTION_INCIDENCIAS' WHERE r.codigo IN ('PROPIETARIO','PROPIETARIO_ESCALERA','PROPIETARIO_SIN_ESCALERA');

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

INSERT INTO unidades (nombre,propietario,coef_general,coef_escalera,tiene_acceso_escalera) VALUES
('Local Comercial','Propietario Local',20,0,0),
('Garaje Privado','Propietario Garaje',20,0,0),
('Piso 1º','Propietario Piso 1º',40,50,1),
('Piso 2º','Propietario Piso 2º',20,50,1);

CREATE TABLE usuarios (
 id INT AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) UNIQUE NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 unidad_id INT NULL,
 rol VARCHAR(50) NOT NULL DEFAULT 'PROPIETARIO_SIN_ESCALERA',
 rol_id INT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_usuarios_unidad (unidad_id),
 INDEX idx_usuarios_rol_id (rol_id),
 FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE SET NULL,
 FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL
);

INSERT INTO usuarios (email,password_hash,unidad_id,rol,rol_id)
SELECT 'admin@comunidad.local','$2y$12$qcwEK955R92FhTjPgq/QlePG2/yE3cWl0HCFgjbTe5V3YKQDKgHM.',3,'ADMINISTRADOR',id
FROM roles WHERE codigo='ADMINISTRADOR';

CREATE TABLE usuario_roles (
 usuario_id INT NOT NULL,
 rol_id INT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (usuario_id,rol_id),
 FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
 FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
);

INSERT INTO usuario_roles (usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u JOIN roles r ON r.codigo=u.rol;

CREATE TABLE propietarios_historial (
 id INT AUTO_INCREMENT PRIMARY KEY,
 unidad_id INT NOT NULL,
 propietario VARCHAR(150) NOT NULL,
 email VARCHAR(190),
 telefono VARCHAR(50),
 fecha_inicio DATE NOT NULL,
 fecha_fin DATE NULL,
 motivo VARCHAR(255),
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_prop_hist_unidad (unidad_id),
 INDEX idx_prop_hist_fechas (fecha_inicio,fecha_fin),
 FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE,
 FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

INSERT INTO propietarios_historial (unidad_id,propietario,email,telefono,fecha_inicio,motivo)
SELECT id,propietario,email,telefono,CURDATE(),'Migración inicial'
FROM unidades;

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
 entidad_tipo VARCHAR(40) NULL,
 entidad_id INT NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_documentos_entidad (entidad_tipo,entidad_id),
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
 convocatoria VARCHAR(100) NULL,
 fecha_inicio DATETIME NOT NULL,
 fecha_fin DATETIME NOT NULL,
 mayoria VARCHAR(30) NOT NULL DEFAULT 'SIMPLE',
 coef_minimo DECIMAL(5,2) NULL,
 quorum_minimo DECIMAL(5,2) NULL,
 estado ENUM('ABIERTA','CERRADA') NOT NULL DEFAULT 'ABIERTA',
 cerrada_at DATETIME NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE votos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 votacion_id INT NOT NULL,
 unidad_id INT NOT NULL,
 voto ENUM('SI','NO','ABSTENCION') NOT NULL,
 coeficiente DECIMAL(8,4) NOT NULL DEFAULT 0,
 comentario VARCHAR(500) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_voto (votacion_id,unidad_id),
 INDEX idx_votos_votacion (votacion_id),
 FOREIGN KEY (votacion_id) REFERENCES votaciones(id) ON DELETE CASCADE,
 FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE
);