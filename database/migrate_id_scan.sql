USE yeb_portal;

-- Verificación de identidad por lectura del reverso del documento (cédula o TI).
-- La imagen se guarda en storage/id-scans y solo se entrega mediante una
-- descarga autorizada; aquí queda el rastro de la verificación.
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_scan_path VARCHAR(255) NULL AFTER cv_uploaded_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_scan_mime VARCHAR(100) NULL AFTER id_scan_path;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_scan_source VARCHAR(20) NULL AFTER id_scan_mime;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_scan_document VARCHAR(20) NULL AFTER id_scan_source;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_scan_data JSON NULL AFTER id_scan_document;
ALTER TABLE users ADD COLUMN IF NOT EXISTS id_scan_verified_at TIMESTAMP NULL AFTER id_scan_data;
