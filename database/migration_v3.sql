-- Migración V3: roles configurables y gestión completa de usuarios
-- Ejecutar DESPUÉS de migration_v2.sql sobre la base de datos existente.

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  sistema TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL
);

CREATE TABLE IF NOT EXISTS rol_permisos (
  rol_id INT NOT NULL,
  permiso_id INT NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
);

INSERT IGNORE INTO roles (codigo,nombre,descripcion,sistema) VALUES
('PRESIDENTE','Presidente','Acceso completo a la administración de la comunidad.',1),
('PROPIETARIO_ESCALERA','Propietario con escalera','Propietario cuya unidad participa en gastos de escalera.',1),
('PROPIETARIO_SIN_ESCALERA','Propietario sin escalera','Propietario cuya unidad no participa en gastos de escalera.',1);

INSERT IGNORE INTO permisos (codigo,nombre,descripcion) VALUES
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
('GESTION_VOTACIONES','Gestionar votaciones','Administrar votaciones y votos.');

INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p WHERE r.codigo='PRESIDENTE';

INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='GESTION_INCIDENCIAS'
WHERE r.codigo IN ('PROPIETARIO_ESCALERA','PROPIETARIO_SIN_ESCALERA');

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS rol_id INT NULL;

UPDATE usuarios u
JOIN roles r ON r.codigo=u.rol
SET u.rol_id=r.id
WHERE u.rol_id IS NULL;

ALTER TABLE usuarios
  MODIFY rol VARCHAR(50) NOT NULL DEFAULT 'PROPIETARIO_SIN_ESCALERA';

ALTER TABLE usuarios ADD INDEX idx_usuarios_rol_id (rol_id);
