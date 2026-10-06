<?php

namespace Modules\Auth\Services;

use App\Core\Database;
use Modules\Auditoria\Services\AuditService;

final class AuthService
{
    public function login(string $email, string $password): bool
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            (new AuditService())->log('login_failed', 'Auth', 'Intento de inicio de sesión rechazado.', 'failed', null, ['email' => strtolower(trim($email))]);
            return false;
        }

        $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role'], 'email' => $user['email'], 'company_id' => $user['company_id'] ? (int) $user['company_id'] : null];
        (new AuditService())->log('login', 'Auth', 'Inicio de sesión exitoso.', 'success', (int) $user['id'], ['role' => $user['role']]);
        return true;
    }

    public function register(string $name, string $email, string $password): bool
    {
        $statement = Database::connection()->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, "candidate")');
        $created = $statement->execute(['name' => trim($name), 'email' => strtolower(trim($email)), 'password' => password_hash($password, PASSWORD_DEFAULT)]);
        if ($created) (new AuditService())->log('register', 'Auth', 'Registro de candidato creado.', 'success', (int) Database::connection()->lastInsertId(), ['email' => strtolower(trim($email))]);
        return $created;
    }
}