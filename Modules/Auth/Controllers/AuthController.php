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
        string $password
    ): bool
    {
        return $this->auth->register($name, $email, $password);
    }

    public function logout(?array $user): void
    {
        $this->auth->logout($user);
    }
}
