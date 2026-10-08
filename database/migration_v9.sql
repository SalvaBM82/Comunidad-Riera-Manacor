-- V9: documentos como archivos locales o enlaces externos.
ALTER TABLE documentos
  MODIFY COLUMN archivo_url VARCHAR(500) NULL,
  ADD COLUMN tipo ENUM('ARCHIVO','ENLACE') NOT NULL DEFAULT 'ENLACE' AFTER categoria,
  ADD COLUMN archivo_path VARCHAR(255) NULL AFTER archivo_url,
  ADD COLUMN archivo_nombre VARCHAR(255) NULL AFTER archivo_path,
  ADD COLUMN archivo_mime VARCHAR(150) NULL AFTER archivo_nombre,
  ADD COLUMN archivo_tamano BIGINT NULL AFTER archivo_mime;

-- Los registros existentes ya son enlaces externos.
UPDATE documentos SET tipo='ENLACE' WHERE tipo IS NULL OR tipo='';
