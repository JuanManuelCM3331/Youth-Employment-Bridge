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
}