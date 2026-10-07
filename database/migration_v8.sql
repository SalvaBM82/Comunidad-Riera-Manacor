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
