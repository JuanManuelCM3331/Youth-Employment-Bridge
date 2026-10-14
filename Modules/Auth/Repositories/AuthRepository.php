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

    public function createCandidate(
        string $name,
        string $email,
        string $password
    ): int {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, email, password, role)
             VALUES (:name, :email, :password, "candidate")'
        );

        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
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
        int $companyId
    ): int {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, email, password, role, company_id)
             VALUES (:name, :email, :password, "recruiter", :company_id)'
        );

        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'company_id' => $companyId,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}