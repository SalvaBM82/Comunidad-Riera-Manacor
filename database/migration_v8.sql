-- Migración V8: separar el cargo de Presidente de la administración web
-- Ejecutar DESPUÉS de migration_v7.sql.
--
-- El Presidente es un cargo de la comunidad, no un administrador técnico
-- de la aplicación. Sus permisos se pueden configurar libremente desde Roles.
-- El rol ADMINISTRADOR conserva la gestión completa de la aplicación.

DELETE rp
FROM rol_permisos rp
JOIN roles r ON r.id=rp.rol_id
JOIN permisos p ON p.id=rp.permiso_id
WHERE r.codigo='PRESIDENTE'
  AND p.codigo IN ('GESTION_USUARIOS','GESTION_ROLES','GESTION_BACKUPS');


-- Corrección de la cuenta administrativa inicial.
INSERT IGNORE INTO roles (codigo,nombre,descripcion,sistema,activo)
VALUES ('ADMINISTRADOR','Administrador','Gestiona la administración web de la comunidad.',1,1);

INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo='ADMINISTRADOR';

UPDATE usuarios u
JOIN roles r ON r.codigo='ADMINISTRADOR'
SET u.rol='ADMINISTRADOR', u.rol_id=r.id
WHERE u.email='admin@comunidad.local';

INSERT IGNORE INTO usuario_roles (usuario_id,rol_id)
SELECT u.id,r.id FROM usuarios u JOIN roles r ON r.codigo='ADMINISTRADOR'
WHERE u.email='admin@comunidad.local';

DELETE ur FROM usuario_roles ur
JOIN usuarios u ON u.id=ur.usuario_id
JOIN roles r ON r.id=ur.rol_id
WHERE u.email='admin@comunidad.local' AND r.codigo='PRESIDENTE';
