USE yeb_portal;

-- Cédula del titular: se valida contra el registro de identidad antes de crear la cuenta.
-- Una misma cédula puede tener una cuenta de candidato y una de empresa,
-- pero no dos cuentas del mismo tipo.
ALTER TABLE users ADD COLUMN IF NOT EXISTS cedula VARCHAR(20) NULL AFTER email;
ALTER TABLE users ADD UNIQUE KEY IF NOT EXISTS unique_users_cedula_role (cedula, role);
