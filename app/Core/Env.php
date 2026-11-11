<?php

namespace App\Core;

/**
 * Cargador centralizado de variables de entorno.
 * Lee el archivo .env y expone helpers tipados para los módulos.
 */
final class Env
{
    private static bool $loaded = false;
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'"))
                )
            ) {
                $value = substr($value, 1, -1);
            }

            if (self::readProcessEnv($key) === null) {
                self::$values[$key] = $value;
                if (function_exists('putenv')) {
                    @putenv($key . '=' . $value);
                }
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $processValue = self::readProcessEnv($key);
        if ($processValue !== null) {
            return $processValue;
        }

        if (array_key_exists($key, self::$values)) {
            $memoryValue = trim(self::$values[$key]);
            if ($memoryValue !== '') {
                return $memoryValue;
            }
        }

        return $default;
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

    private static function readProcessEnv(string $key): ?string
    {
        if (function_exists('getenv')) {
            $value = getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ([$_ENV, $_SERVER] as $source) {
            if (isset($source[$key]) && is_string($source[$key]) && trim($source[$key]) !== '') {
                return trim($source[$key]);
            }
        }

        return null;
    }
}
