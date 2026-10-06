USE yeb_portal;

ALTER TABLE jobs MODIFY work_mode VARCHAR(20) NOT NULL DEFAULT 'Hybrid';
UPDATE jobs SET work_mode = 'Hybrid' WHERE work_mode NOT IN ('Remote', 'Presencial');
ALTER TABLE jobs MODIFY work_mode ENUM('Remote','Hybrid','Presencial') NOT NULL DEFAULT 'Hybrid';
ALTER TABLE users ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER role;
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