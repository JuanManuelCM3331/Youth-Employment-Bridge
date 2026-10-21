<?php

namespace App\Core;

/**
 * Cargador centralizado de variables de entorno.
 * Lee el archivo .env y expone helpers tipados para los módulos.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (($value[0] ?? '') === '"' && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        if ($value === false || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    public static function float(string $key, float $default): float
    {
        $value = self::get($key);

        return $value === null ? $default : (float) $value;
    }
}
