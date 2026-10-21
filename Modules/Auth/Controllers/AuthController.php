<?php

namespace Modules\Auth\Controllers;

use Modules\Auth\Services\AuthService;

final class AuthController
{
    public function __construct(private AuthService $auth)
    {
    }

    public function login(string $email, string $password): bool
    {
        return $this->auth->login($email, $password);
    }

    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $cedula,
        string $birthDate
    ): bool
    {
        return $this->auth->register($name, $email, $password, $passwordConfirm, $cedula, $birthDate);
    }

    public function logout(?array $user): void
    {
        $this->auth->logout($user);
    }

    public function registerCompany(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $companyName,
        string $city,
        string $cedula,
        string $birthDate = ''
    ): bool {
        return $this->auth->registerCompany(
            $name,
            $email,
            $password,
            $passwordConfirm,
            $companyName,
            $city,
            $cedula,
            $birthDate
        );
    }

    /**
     * Cambia la contraseña de la cuenta con sesión activa.
     */
    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $passwordConfirm
    ): void {
        $this->auth->changePassword($userId, $currentPassword, $newPassword, $passwordConfirm);
    }
}
