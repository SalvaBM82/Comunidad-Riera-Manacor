-- Migración V7: copias de seguridad
-- Ejecutar DESPUÉS de migration_v6.sql.

INSERT IGNORE INTO permisos (codigo,nombre,descripcion)
VALUES ('GESTION_BACKUPS','Gestionar copias de seguridad','Crear y restaurar copias completas de la base de datos.');

INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo IN ('PRESIDENTE','ADMINISTRADOR')
  AND p.codigo='GESTION_BACKUPS';
