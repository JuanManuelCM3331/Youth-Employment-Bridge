-- Migración para soporte de aspirantes menores de edad (16 y 17 años)
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_minor TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS minor_blocked TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS birth_date DATE NULL;

CREATE TABLE IF NOT EXISTS user_minor_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    civil_registry_path VARCHAR(255) NOT NULL,
    civil_registry_name VARCHAR(255) NOT NULL,
    civil_registry_mime VARCHAR(100) NOT NULL,
    parent_id_path VARCHAR(255) NOT NULL,
    parent_id_name VARCHAR(255) NOT NULL,
    parent_id_mime VARCHAR(100) NOT NULL,
    authorization_letter_path VARCHAR(255) NOT NULL,
    authorization_letter_name VARCHAR(255) NOT NULL,
    authorization_letter_mime VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_minor_docs_user (user_id)
);
