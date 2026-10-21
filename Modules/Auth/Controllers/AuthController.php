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
        string $cedula,
        string $birthDate
    ): bool
    {
        return $this->auth->register($name, $email, $password, $cedula, $birthDate);
    }

    public function logout(?array $user): void
    {
        $this->auth->logout($user);
    }

    public function registerCompany(
        string $name,
        string $email,
        string $password,
        string $companyName,
        string $city,
        string $cedula,
        string $birthDate = ''
    ): bool {
        return $this->auth->registerCompany($name, $email, $password, $companyName, $city, $cedula, $birthDate);
    }
}
