USE yeb_portal;

ALTER TABLE jobs MODIFY work_mode VARCHAR(20) NOT NULL DEFAULT 'Hybrid';
UPDATE jobs SET work_mode = 'Hybrid' WHERE work_mode NOT IN ('Remote', 'Presencial');
ALTER TABLE jobs MODIFY work_mode ENUM('Remote','Hybrid','Presencial') NOT NULL DEFAULT 'Hybrid';
ALTER TABLE users ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_path VARCHAR(255) NULL AFTER bio;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_original_name VARCHAR(255) NULL AFTER cv_path;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_mime VARCHAR(100) NULL AFTER cv_original_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_size INT UNSIGNED NULL AFTER cv_mime;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_uploaded_at TIMESTAMP NULL AFTER cv_size;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(40) NULL AFTER bio;
ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(120) NULL AFTER phone;
ALTER TABLE users ADD COLUMN IF NOT EXISTS professional_title VARCHAR(160) NULL AFTER location;
ALTER TABLE users ADD COLUMN IF NOT EXISTS skills TEXT NULL AFTER professional_title;
ALTER TABLE users ADD COLUMN IF NOT EXISTS experience TEXT NULL AFTER skills;
ALTER TABLE users ADD COLUMN IF NOT EXISTS education TEXT NULL AFTER experience;
ALTER TABLE users ADD COLUMN IF NOT EXISTS linkedin_url VARCHAR(255) NULL AFTER education;
ALTER TABLE users ADD COLUMN IF NOT EXISTS portfolio_url VARCHAR(255) NULL AFTER linkedin_url;
ALTER TABLE users ADD COLUMN IF NOT EXISTS availability VARCHAR(80) NULL AFTER portfolio_url;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo_path VARCHAR(255) NULL AFTER availability;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo_mime VARCHAR(100) NULL AFTER profile_photo_path;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS industry VARCHAR(120) NULL AFTER city;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS website VARCHAR(255) NULL AFTER industry;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS phone VARCHAR(40) NULL AFTER website;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS contact_email VARCHAR(180) NULL AFTER phone;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS size VARCHAR(80) NULL AFTER contact_email;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS profile_photo_path VARCHAR(255) NULL AFTER size;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS profile_photo_mime VARCHAR(100) NULL AFTER profile_photo_path;
CREATE TABLE IF NOT EXISTS saved_jobs (
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS hidden_jobs (
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
SET @constraint_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'users_company_id_foreign'
);
SET @add_constraint = IF(@constraint_exists = 0,
    'ALTER TABLE users ADD CONSTRAINT users_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE add_constraint_statement FROM @add_constraint;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

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
);