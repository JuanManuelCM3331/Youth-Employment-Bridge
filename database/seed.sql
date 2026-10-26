USE yeb_portal;

-- ---------------------------------------------------------------------------
-- Datos de demostración (idempotente: se puede ejecutar varias veces).
--
-- Las cédulas demo están FUERA del simulador de prueba
-- (`Modules\Identity\Providers\SandboxCedulaProvider`) para que todas las
-- cédulas de prueba queden libres para nuevos registros.
-- ---------------------------------------------------------------------------

INSERT INTO users (name, email, cedula, password, role) VALUES
('Ana Candidata', 'ana@yeb.test', '8001020304', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'candidate'),
('Laura Recruiter', 'laura@talentolab.test', '9002030405', '$2y$10$eoaNaI48PD.K0SqGEGymzuvRnlRg6Qf8Kewm6/GOfF4U1MEQINGKW', 'recruiter')
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role), cedula = VALUES(cedula);

-- Usuario de prueba menor de edad (entre 16 y 18 años, mismo rol candidate)
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

-- Vincula la cuenta de reclutador con la empresa demo.
-- Usa COALESCE para que nunca quede con company_id NULL si el nombre de la
-- empresa fue editado (por ejemplo "Talento Lab Demo") o si no existe.
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
