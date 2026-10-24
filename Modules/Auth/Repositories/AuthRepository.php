<?php

namespace Modules\Auth\Repositories;

use App\Core\Database;

final class AuthRepository
{
    public function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );

        $statement->execute(['email' => $email]);

        return $statement->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    /**
     * Fecha del último inicio de sesión exitoso de la cuenta.
     *
     * Sirve para reiniciar el conteo de intentos fallidos: solo cuentan los
     * fallos posteriores al último acceso correcto.
     */
    public function lastSuccessfulLoginAt(int $userId): ?string
    {
        $statement = Database::connection()->prepare(
            "SELECT MAX(created_at) FROM audit_logs
             WHERE action = 'login' AND status = 'success' AND user_id = :user_id"
        );

        $statement->execute(['user_id' => $userId]);
        $lastLogin = $statement->fetchColumn();

        return $lastLogin !== false && $lastLogin !== null ? (string) $lastLogin : null;
    }

    /**
     * Cuenta los intentos fallidos recientes de un correo.
     *
     * Se apoya en la auditoría que ya registra cada rechazo de login, de modo
     * que el bloqueo funciona incluso para correos que no existen.
     */
    public function countRecentFailedLogins(string $email, ?string $since, int $windowMinutes): int
    {
        $sql = "SELECT COUNT(*) FROM audit_logs
                WHERE action = 'login_failed' AND status = 'failed'
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = :email
                  AND created_at >= DATE_SUB(NOW(), INTERVAL " . max(1, $windowMinutes) . ' MINUTE)';

        if ($since !== null) {
            $sql .= ' AND created_at > :since';
        }

        $statement = Database::connection()->prepare($sql);
        $params = ['email' => $email];

        if ($since !== null) {
            $params['since'] = $since;
        }

        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Guarda el hash de la nueva contraseña de una cuenta.
     */
    public function updatePassword(int $id, string $hashedPassword): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET password = :password WHERE id = :id'
        );

        $statement->execute([
            'password' => $hashedPassword,
            'id' => $id,
        ]);
    }

    /**
     * Una misma cédula puede tener una cuenta de candidato y una de empresa,
     * pero no dos cuentas del mismo tipo.
     */
    public function findByCedulaAndRole(string $cedula, string $role): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE cedula = :cedula AND role = :role LIMIT 1'
        );

        $statement->execute([
            'cedula' => $cedula,
            'role' => $role,
        ]);

        return $statement->fetch() ?: null;
    }

    /**
     * Devuelve las cédulas que ya tienen una cuenta registrada.
     *
     * @param string[] $documents
     * @return string[]
     */
    public function findExistingCedulas(array $documents): array
    {
        $documents = array_values(array_filter(array_map(
            static fn (string $document): string => trim($document),
            $documents
        )));

        if ($documents === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($documents), '?'));
        $statement = Database::connection()->prepare(
            'SELECT DISTINCT cedula FROM users WHERE cedula IN (' . $placeholders . ')'
        );
        $statement->execute($documents);

        return array_map(static fn (array $row): string => (string) $row['cedula'], $statement->fetchAll());
    }

    /**
     * Crea la cuenta de un candidato con el documento de identidad ya validado.
     *
     * @param array<string, mixed>|null $scan Metadatos del reverso almacenado.
     */
    public function createCandidate(
        string $name,
        string $email,
        string $password,
        string $cedula,
        ?array $scan = null
    ): int {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, email, password, role, cedula,
                                id_scan_path, id_scan_mime, id_scan_source,
                                id_scan_document, id_scan_data, id_scan_verified_at)
             VALUES (:name, :email, :password, "candidate", :cedula,
                     :scan_path, :scan_mime, :scan_source,
                     :scan_document, :scan_data, NOW())'
        );

        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'cedula' => $cedula,
            'scan_path' => $scan['path'] ?? null,
            'scan_mime' => $scan['mime'] ?? null,
            'scan_source' => $scan['source'] ?? null,
            'scan_document' => $scan['document'] ?? null,
            'scan_data' => $scan['data'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function createCompany(string $name, string $city): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO companies (name, city) VALUES (:name, :city)'
        );

        $statement->execute([
            'name' => $name,
            'city' => $city,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function createRecruiter(
        string $name,
        string $email,
        string $password,
        int $companyId,
        string $cedula
    ): int {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, email, password, role, company_id, cedula)
             VALUES (:name, :email, :password, "recruiter", :company_id, :cedula)'
        );

        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'company_id' => $companyId,
            'cedula' => $cedula,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}