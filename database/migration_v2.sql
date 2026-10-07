-- Migración V2: administración, roles y cambio de propietario
-- Ejecutar sobre la base de datos EXISTENTE. No volver a importar database.sql.

ALTER TABLE usuarios
  MODIFY rol ENUM('PRESIDENTE','PROPIETARIO_ESCALERA','PROPIETARIO_SIN_ESCALERA') NOT NULL DEFAULT 'PROPIETARIO_SIN_ESCALERA';

UPDATE usuarios u
LEFT JOIN unidades un ON un.id=u.unidad_id
SET u.rol = CASE
  WHEN u.rol='PRESIDENTE' THEN 'PRESIDENTE'
  WHEN COALESCE(un.tiene_acceso_escalera,0)=1 THEN 'PROPIETARIO_ESCALERA'
  ELSE 'PROPIETARIO_SIN_ESCALERA'
END;

CREATE TABLE IF NOT EXISTS propietarios_historial (
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
  FOREIGN KEY (unidad_id) REFERENCES unidades(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_prop_hist_unidad (unidad_id),
  INDEX idx_prop_hist_fechas (fecha_inicio,fecha_fin)
);

INSERT INTO propietarios_historial
(unidad_id,propietario,email,telefono,fecha_inicio,motivo)
SELECT u.id,u.propietario,u.email,u.telefono,CURDATE(),'Migración inicial V2'
FROM unidades u
WHERE NOT EXISTS (
  SELECT 1 FROM propietarios_historial h WHERE h.unidad_id=u.id
);

CREATE INDEX idx_usuarios_unidad ON usuarios(unidad_id);
