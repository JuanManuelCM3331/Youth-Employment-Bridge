CREATE DATABASE IF NOT EXISTS yeb_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yeb_portal;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','recruiter','candidate') NOT NULL DEFAULT 'candidate',
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
    cv_path VARCHAR(255) NULL,
    cv_original_name VARCHAR(255) NULL,
    cv_mime VARCHAR(100) NULL,
    cv_size INT UNSIGNED NULL,
    cv_uploaded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
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
    verified TINYINT(1) NOT NULL DEFAULT 1,
    logo_color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE users ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL AFTER role;
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
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','review','interview','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_application (job_id, user_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message VARCHAR(255) NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS cities (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) UNIQUE NOT NULL);
CREATE TABLE IF NOT EXISTS skills (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) UNIQUE NOT NULL);
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