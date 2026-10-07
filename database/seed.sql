USE yeb_portal;
INSERT INTO users (name, email, password, role) VALUES
('Ana Candidata', 'ana@yeb.test', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'candidate'),
('Laura Recruiter', 'laura@talentolab.test', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'recruiter')
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role);
INSERT INTO companies (name, description, city, logo_color) VALUES
('Talento Lab', 'Equipo que conecta talento joven con empresas de impacto.', 'Bogotá', '#2563eb'),
('Verde Digital', 'Producto y tecnología para ciudades sostenibles.', 'Medellín', '#059669')
ON DUPLICATE KEY UPDATE description = VALUES(description);
UPDATE users SET company_id = (SELECT id FROM companies WHERE name = 'Talento Lab' LIMIT 1) WHERE email = 'laura@talentolab.test';
INSERT INTO users (name, email, password, role) VALUES
('Administrador YEB', 'admin@yeb.test', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role);
INSERT INTO jobs (company_id, title, description, city, work_mode, experience, salary_min, salary_max)
SELECT id, 'Frontend Developer Junior', 'Construye interfaces accesibles y aprende junto a un equipo senior.', 'Bogotá', 'Hybrid', '0 - 1 año', 2800000, 3800000
FROM companies WHERE name = 'Talento Lab' LIMIT 1;
INSERT INTO jobs (company_id, title, description, city, work_mode, experience, salary_min, salary_max)
SELECT id, 'Analista de datos', 'Convierte datos de negocio en decisiones claras para nuestros clientes.', 'Remoto', 'Remote', 'Inicial', 3000000, 4200000
FROM companies WHERE name = 'Talento Lab' LIMIT 1;
INSERT INTO jobs (company_id, title, description, city, work_mode, experience, salary_min, salary_max)
SELECT id, 'Diseñador UX/UI', 'Diseña experiencias digitales simples para servicios públicos.', 'Medellín', 'Presencial', '1 - 2 años', 3200000, 4500000
FROM companies WHERE name = 'Verde Digital' LIMIT 1;