USE yeb_portal;

-- Almacenamiento de archivos binarios en la base de datos (BLOB).
-- Aplica este script sobre instalaciones existentes.
--
-- Requisito del servidor: max_allowed_packet debe ser mayor que el archivo más
-- grande permitido (10 MB para documentos de menores). Se recomienda 64M en
-- my.ini y reiniciar MySQL. También puede aplicarse en caliente con:
--   SET GLOBAL max_allowed_packet = 67108864;

ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo_data LONGBLOB NULL AFTER profile_photo_mime;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_data LONGBLOB NULL AFTER cv_mime;

ALTER TABLE companies ADD COLUMN IF NOT EXISTS profile_photo_data LONGBLOB NULL AFTER profile_photo_mime;

ALTER TABLE user_minor_documents ADD COLUMN IF NOT EXISTS civil_registry_data LONGBLOB NULL AFTER civil_registry_mime;
ALTER TABLE user_minor_documents ADD COLUMN IF NOT EXISTS parent_id_data LONGBLOB NULL AFTER parent_id_mime;
ALTER TABLE user_minor_documents ADD COLUMN IF NOT EXISTS authorization_letter_data LONGBLOB NULL AFTER authorization_letter_mime;
