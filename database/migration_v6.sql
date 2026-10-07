-- Migración V6: módulo completo de votaciones
-- Ejecutar DESPUÉS de migration_v5.sql.

ALTER TABLE votaciones
  ADD COLUMN convocatoria VARCHAR(100) NULL,
  ADD COLUMN mayoria VARCHAR(30) NOT NULL DEFAULT 'SIMPLE',
  ADD COLUMN coef_minimo DECIMAL(5,2) NULL,
  ADD COLUMN quorum_minimo DECIMAL(5,2) NULL,
  ADD COLUMN cerrada_at DATETIME NULL,
  ADD COLUMN created_by INT NULL,
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE votos
  ADD COLUMN comentario VARCHAR(500) NULL;

ALTER TABLE votos
  ADD INDEX idx_votos_votacion (votacion_id);

ALTER TABLE documentos
  MODIFY entidad_tipo VARCHAR(40) NULL;

-- Las votaciones también pueden tener múltiples documentos externos.
-- Usarán entidad_tipo='votacion' y entidad_id=<id de votación>.
