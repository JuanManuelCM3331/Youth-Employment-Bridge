-- =============================================================================
-- Youth Employment Bridge (YEB) v1.0.0 - Script SQL unificado para InfinityFree
-- =============================================================================
-- Incluye el esquema completo + las 7 migraciones + datos semilla iniciales.
-- Sin CREATE DATABASE ni USE para importar directamente en phpMyAdmin sobre
-- la base de datos creada en el panel de InfinityFree.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    description TEXT NULL,
    city VARCHAR(100) NOT NULL,
    industry VARCHAR(120) NULL,
    website VARCHAR(255) NULL,
    phone VARCHAR(40) NULL,
    contact_email VARCHAR(180) NULL,
    size VARCHAR(80) NULL,
    profile_photo_path VARCHAR(255) NULL,
    profile_photo_mime VARCHAR(100) NULL,
    profile_photo_data LONGBLOB NULL,
    verified TINYINT(1) NOT NULL DEFAULT 1,
    logo_color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    cedula VARCHAR(20) NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','recruiter','candidate') NOT NULL DEFAULT 'candidate',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    is_minor TINYINT(1) NOT NULL DEFAULT 0,
    minor_blocked TINYINT(1) NOT NULL DEFAULT 0,
    birth_date DATE NULL,
    company_id INT UNSIGNED NULL,
    bio TEXT NULL,
    phone VARCHAR(40) NULL,
    location VARCHAR(120) NULL,
    professional_title VARCHAR(160) NULL,
    skills TEXT NULL,
    experience TEXT NULL,
    education TEXT NULL,
    linkedin_url VARCHAR(255) NULL,
    portfolio_url VARCHAR(255) NULL,
    availability VARCHAR(80) NULL,
    profile_photo_path VARCHAR(255) NULL,
    profile_photo_mime VARCHAR(100) NULL,
    profile_photo_data LONGBLOB NULL,
    cv_path VARCHAR(255) NULL,
    cv_original_name VARCHAR(255) NULL,
    cv_mime VARCHAR(100) NULL,
    cv_data LONGBLOB NULL,
    cv_size INT UNSIGNED NULL,
    cv_uploaded_at TIMESTAMP NULL,
    id_scan_path VARCHAR(255) NULL,
    id_scan_mime VARCHAR(100) NULL,
    id_scan_source VARCHAR(20) NULL,
    id_scan_document VARCHAR(20) NULL,
    id_scan_data JSON NULL,
    id_scan_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_users_cedula_role (cedula, role),
    CONSTRAINT users_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_minor_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    civil_registry_path VARCHAR(255) NOT NULL,
    civil_registry_name VARCHAR(255) NOT NULL,
    civil_registry_mime VARCHAR(100) NOT NULL,
    civil_registry_data LONGBLOB NULL,
    parent_id_path VARCHAR(255) NOT NULL,
    parent_id_name VARCHAR(255) NOT NULL,
    parent_id_mime VARCHAR(100) NOT NULL,
    parent_id_data LONGBLOB NULL,
    authorization_letter_path VARCHAR(255) NOT NULL,
    authorization_letter_name VARCHAR(255) NOT NULL,
    authorization_letter_mime VARCHAR(100) NOT NULL,
    authorization_letter_data LONGBLOB NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_minor_docs_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    work_mode ENUM('Remote','Hybrid','Presencial') NOT NULL DEFAULT 'Hybrid',
    experience VARCHAR(80) NOT NULL DEFAULT 'Inicial',
    salary_min DECIMAL(12,2) NULL,
    salary_max DECIMAL(12,2) NULL,
    status ENUM('draft','published','closed') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','review','interview','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_application (job_id, user_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message VARCHAR(255) NOT NULL,
    title VARCHAR(150) NULL,
    url VARCHAR(255) NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    device_token VARCHAR(100) NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NULL,
    auth VARCHAR(255) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_push_user (user_id),
    INDEX idx_push_device_token (device_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    module VARCHAR(80) NOT NULL,
    route VARCHAR(255) NOT NULL,
    status ENUM('success','failed','error') NOT NULL DEFAULT 'success',
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX audit_created_at (created_at),
    INDEX audit_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_jobs (
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hidden_jobs (
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_messages_app (application_id),
    INDEX idx_messages_sender (sender_id),
    INDEX idx_messages_created (created_at),
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Datos iniciales de demostración (idempotente)
-- ---------------------------------------------------------------------------

INSERT INTO users (name, email, cedula, password, role) VALUES
('Ana Candidata', 'ana@yeb.test', '8001020304', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'candidate'),
('Laura Recruiter', 'laura@talentolab.test', '9002030405', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'recruiter')
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role), cedula = VALUES(cedula);

INSERT INTO users (name, email, cedula, password, role, is_minor, minor_blocked, birth_date, status) VALUES
('Mateo Gómez (Menor de Edad)', 'menor@yeb.test', '1099887766', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'candidate', 1, 1, '2009-04-15', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role), cedula = VALUES(cedula), is_minor = VALUES(is_minor), minor_blocked = VALUES(minor_blocked), birth_date = VALUES(birth_date), status = VALUES(status);

INSERT INTO companies (name, description, city, logo_color)
SELECT 'Talento Lab', 'Equipo que conecta talento joven con empresas de impacto.', 'Bogotá', '#2563eb'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Talento Lab')
LIMIT 1;

INSERT INTO companies (name, description, city, logo_color)
SELECT 'Verde Digital', 'Producto y tecnología para ciudades sostenibles.', 'Medellín', '#059669'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Verde Digital')
LIMIT 1;

UPDATE users SET company_id = COALESCE(
    (SELECT id FROM companies WHERE name IN ('Talento Lab Demo', 'Talento Lab') ORDER BY id LIMIT 1),
    (SELECT id FROM companies ORDER BY id LIMIT 1)
) WHERE email = 'laura@talentolab.test';

INSERT INTO users (name, email, password, role) VALUES
('Administrador YEB', 'admin@yeb.test', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role);

INSERT INTO jobs (company_id, title, description, city, work_mode, experience, salary_min, salary_max)
SELECT c.id, 'Frontend Developer Junior', 'Construye interfaces accesibles y aprende junto a un equipo senior.', 'Bogotá', 'Hybrid', '0 - 1 año', 2800000, 3800000
FROM companies c
WHERE c.name = 'Talento Lab'
  AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.company_id = c.id AND j.title = 'Frontend Developer Junior')
LIMIT 1;

INSERT INTO jobs (company_id, title, description, city, work_mode, experience, salary_min, salary_max)
SELECT c.id, 'Analista de datos', 'Convierte datos de negocio en decisiones claras para nuestros clientes.', 'Remoto', 'Remote', 'Inicial', 3000000, 4200000
FROM companies c
WHERE c.name = 'Talento Lab'
  AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.company_id = c.id AND j.title = 'Analista de datos')
LIMIT 1;

INSERT INTO jobs (company_id, title, description, city, work_mode, experience, salary_min, salary_max)
SELECT c.id, 'Diseñador UX/UI', 'Diseña experiencias digitales simples para servicios públicos.', 'Medellín', 'Presencial', '1 - 2 años', 3200000, 4500000
FROM companies c
WHERE c.name = 'Verde Digital'
  AND NOT EXISTS (SELECT 1 FROM jobs j WHERE j.company_id = c.id AND j.title = 'Diseñador UX/UI')
LIMIT 1;
