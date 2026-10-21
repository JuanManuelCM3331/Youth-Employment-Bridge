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

    public function createCandidate(
        string $name,
        string $email,
        string $password,
        string $cedula
    ): int {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, email, password, role, cedula)
             VALUES (:name, :email, :password, "candidate", :cedula)'
        );

        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'cedula' => $cedula,
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