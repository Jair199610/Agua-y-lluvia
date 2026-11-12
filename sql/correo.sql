-- Activa las alertas por correo: agrega a cada usuario la colonia donde vive.
-- Importar UNA sola vez, estando DENTRO de la base aqua_sentinel.
-- No borra nada. Si sale el error "Duplicate column name", es que ya estaba importado.

ALTER TABLE usuarios
  ADD COLUMN id_colonia INT NULL AFTER id_rol,
  ADD CONSTRAINT usuarios_ibfk_colonia FOREIGN KEY (id_colonia) REFERENCES colonias (id_colonia) ON DELETE SET NULL;
