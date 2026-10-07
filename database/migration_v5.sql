-- Migración V5: documentos externos múltiples asociados a cualquier entidad
-- Ejecutar DESPUÉS de migration_v4.sql.

ALTER TABLE documentos
  ADD COLUMN entidad_tipo VARCHAR(40) NULL,
  ADD COLUMN entidad_id INT NULL;

CREATE INDEX idx_documentos_entidad ON documentos(entidad_tipo, entidad_id);

-- Los documentos generales de la comunidad siguen teniendo entidad_tipo/entidad_id NULL.
-- Los documentos asociados se guardan como enlaces externos HTTP/HTTPS y pueden ser múltiples por entidad.
