-- Migración V4: múltiples roles por usuario y roles típicos de comunidad
-- Ejecutar DESPUÉS de migration_v3.sql sobre la base de datos existente.

CREATE TABLE IF NOT EXISTS usuario_roles (
  usuario_id INT NOT NULL,
  rol_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, rol_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Roles habituales en una comunidad de propietarios.
INSERT IGNORE INTO roles (codigo,nombre,descripcion,sistema,activo) VALUES
('PRESIDENTE','Presidente','Cargo de presidencia de la comunidad. Puede acumularse con otros roles.',1,1),
('VICEPRESIDENTE','Vicepresidente','Sustituye al Presidente cuando corresponda.',1,1),
('ADMINISTRADOR','Administrador','Gestiona la administración ordinaria de la comunidad.',1,1),
('SECRETARIO','Secretario','Gestiona documentación, actas y tareas de secretaría.',1,1),
('TESORERO','Tesorero','Gestiona la parte económica y de cobros de la comunidad.',1,1),
('VOCAL','Vocal','Participa en la gestión y seguimiento de asuntos de la comunidad.',1,1),
('PROPIETARIO','Propietario','Acceso básico como propietario de una unidad.',1,1),
('PROPIETARIO_ESCALERA','Propietario con escalera','Propietario cuya unidad participa en gastos de escalera.',1,1),
('PROPIETARIO_SIN_ESCALERA','Propietario sin escalera','Propietario cuya unidad no participa en gastos de escalera.',1,1);

-- Presidente: todos los permisos.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo='PRESIDENTE';

-- Administrador: gestión integral.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p
WHERE r.codigo='ADMINISTRADOR';

-- Secretario: documentación, incidencias y votaciones.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p
  ON p.codigo IN ('GESTION_DOCUMENTOS','GESTION_INCIDENCIAS','GESTION_VOTACIONES')
WHERE r.codigo='SECRETARIO';

-- Tesorero: gestión económica.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p
  ON p.codigo IN ('GESTION_GASTOS','GESTION_PRESUPUESTOS','GESTION_RECIBOS','GESTION_DERRAMAS','VER_MOROSIDAD')
WHERE r.codigo='TESORERO';

-- Vicepresidente: gestión operativa y sustitución.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p
  ON p.codigo IN ('GESTION_INCIDENCIAS','GESTION_DOCUMENTOS','GESTION_VOTACIONES')
WHERE r.codigo='VICEPRESIDENTE';

-- Vocal: seguimiento de incidencias y documentación/votaciones.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p
  ON p.codigo IN ('GESTION_INCIDENCIAS','GESTION_DOCUMENTOS','GESTION_VOTACIONES')
WHERE r.codigo='VOCAL';

-- Propietarios: pueden registrar incidencias. La condición de escalera
-- sigue siendo una característica de la unidad, no una obligación de rol.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r JOIN permisos p ON p.codigo='GESTION_INCIDENCIAS'
WHERE r.codigo IN ('PROPIETARIO','PROPIETARIO_ESCALERA','PROPIETARIO_SIN_ESCALERA');

-- Migración de los roles existentes de V3 a la tabla N:M.
INSERT IGNORE INTO usuario_roles (usuario_id,rol_id)
SELECT u.id,u.rol_id
FROM usuarios u
WHERE u.rol_id IS NOT NULL;

-- Todos los usuarios existentes deben conservar al menos un rol.
-- El rol antiguo queda sincronizado en rol/rol_id por compatibilidad.
