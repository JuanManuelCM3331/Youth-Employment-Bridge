<?php

namespace App\Core;

/**
 * Inicio de sesión HTTP con cookies endurecidas.
 *
 * Se configura antes de session_start() para que PHPSESSID viaje con
 * HttpOnly y SameSite=Lax, y con Secure cuando la petición llega por HTTPS.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = $_SERVER['HTTPS'] ?? '';
        $isHttps = $https !== '' && strtolower((string) $https) !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}
