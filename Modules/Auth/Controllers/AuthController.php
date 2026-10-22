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

    /**
     * Registra un candidato validando el reverso de su documento de identidad.
     *
     * @param array $scan ['data' => json con la lectura, 'file' => imagen subida]
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $cedula,
        string $birthDate,
        array $scan = []
    ): bool
    {
        return $this->auth->register(
            $name,
            $email,
            $password,
            $passwordConfirm,
            $cedula,
            $birthDate,
            $scan
        );
    }

    public function logout(?array $user): void
    {
        $this->auth->logout($user);
    }

    /**
     * Registra una empresa y su reclutador (sin requisitos de edad).
     */
    public function registerCompany(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $companyName,
        string $city,
        string $cedula
    ): bool {
        return $this->auth->registerCompany(
            $name,
            $email,
            $password,
            $passwordConfirm,
            $companyName,
            $city,
            $cedula
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
