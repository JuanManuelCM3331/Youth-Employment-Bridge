<?php

namespace Modules\Auth\Services;

use InvalidArgumentException;
use Modules\Auditoria\Services\AuditService;
use Modules\Auth\Repositories\AuthRepository;

final class AuthService
{
    public function __construct(
        private AuthRepository $users,
        private AuditService $audit
    ) {
    }

    public function login(string $email, string $password): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->users->findByEmail($normalizedEmail);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->audit->log(
                'login_failed',
                'Auth',
                'Intento de inicio de sesión rechazado.',
                'failed',
                null,
                ['email' => $normalizedEmail]
            );

            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'email' => $user['email'],
            'company_id' => $user['company_id']
                ? (int) $user['company_id']
                : null,
        ];

        $this->audit->log(
            'login',
            'Auth',
            'Inicio de sesión exitoso.',
            'success',
            (int) $user['id'],
            ['role' => $user['role']]
        );

        return true;
    }

    public function register(string $name, string $email, string $password): bool
    {
        $normalizedName = trim($name);
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedName === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos 6 caracteres.'
            );
        }

        if ($this->users->findByEmail($normalizedEmail)) {
            throw new InvalidArgumentException(
                'El correo electrónico ya está registrado.'
            );
        }

        $userId = $this->users->createCandidate(
            $normalizedName,
            $normalizedEmail,
            password_hash($password, PASSWORD_DEFAULT)
        );

        $this->audit->log(
            'register',
            'Auth',
            'Registro de candidato creado.',
            'success',
            $userId,
            ['email' => $normalizedEmail]
        );

        return true;
    }

    public function logout(?array $user): void
    {
        if ($user) {
            $this->audit->log(
                'logout',
                'Auth',
                'Cierre de sesión.',
                'success',
                (int) $user['id']
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parameters['path'],
                $parameters['domain'],
                $parameters['secure'],
                $parameters['httponly']
            );
        }

        session_destroy();
    }
}