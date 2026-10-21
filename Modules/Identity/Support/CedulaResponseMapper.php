<?php

namespace Modules\Identity\Support;

use DateTimeImmutable;
use Modules\Identity\Dto\CedulaRecord;

/**
 * Normaliza la respuesta de los distintos proveedores de cédulas a un
 * formato único: si la persona existe, su nombre y su fecha de nacimiento.
 */
final class CedulaResponseMapper
{
    private const NAME_KEYS = [
        'nombre', 'nombre_completo', 'nombrecompleto', 'full_name', 'fullname',
        'nombre_titular', 'nombre_registrado', 'nombre_persona', 'name', 'person_name',
        'apellidos_y_nombres',
    ];

    private const BIRTH_DATE_KEYS = [
        'fecha_nacimiento', 'fechanacimiento', 'nacimiento', 'birth_date', 'birthdate',
        'date_of_birth', 'dateofbirth', 'dob', 'fecha_nac', 'birth',
    ];

    private const AGE_KEYS = ['edad', 'age', 'edad_actual', 'edad_actual_anos'];

    private const EXISTS_KEYS = [
        'existe', 'exists', 'found', 'found_person', 'encontrado', 'encontro',
        'valid', 'is_valid', 'valid_document', 'document_valid', 'existe_persona',
        'has_data', 'coincide',
    ];

    private const STATUS_KEYS = ['status', 'estado', 'status_code', 'resultado', 'code'];

    private const FALSE_VALUES = [
        '0', 'false', 'no', 'null', 'none', 'not found', 'not_found',
        'no_encontrado', 'no existe', 'no_found', '404',
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function map(array $payload, string $source = 'http'): CedulaRecord
    {
        $name = $this->findString($payload, self::NAME_KEYS);
        $birthDate = $this->findDate($payload, self::BIRTH_DATE_KEYS);
        $age = $this->findInt($payload, self::AGE_KEYS);

        if (!$this->resolveExistence($payload, $name)) {
            return CedulaRecord::notFound($source);
        }

        return CedulaRecord::found($name ?? '', $birthDate, $age, $source, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveExistence(array $payload, ?string $name): bool
    {
        $flag = $this->findBool($payload, self::EXISTS_KEYS);

        if ($flag !== null) {
            return $flag;
        }

        if ($name !== null && $name !== '') {
            return true;
        }

        $status = $this->findString($payload, self::STATUS_KEYS);

        if ($status !== null && in_array(strtolower(trim($status)), self::FALSE_VALUES, true)) {
            return false;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $keys
     */
    private function findString(array $payload, array $keys, int $depth = 0): ?string
    {
        foreach ($payload as $key => $value) {
            if (!in_array(strtolower((string) $key), $keys, true)) {
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $nested = $this->findString($value, $keys, $depth + 1);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        if ($depth >= 4) {
            return null;
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->findString($value, $keys, $depth + 1);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $keys
     */
    private function findInt(array $payload, array $keys, int $depth = 0): ?int
    {
        foreach ($payload as $key => $value) {
            if (!in_array(strtolower((string) $key), $keys, true)) {
                continue;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        if ($depth >= 4) {
            return null;
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->findInt($value, $keys, $depth + 1);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $keys
     */
    private function findBool(array $payload, array $keys, int $depth = 0): ?bool
    {
        foreach ($payload as $key => $value) {
            if (!in_array(strtolower((string) $key), $keys, true)) {
                continue;
            }

            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value !== 0;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));

                if (in_array($normalized, self::FALSE_VALUES, true)) {
                    return false;
                }

                if (in_array($normalized, ['1', 'true', 'yes', 'si', 'sí', 'ok', 'found', 'existe'], true)) {
                    return true;
                }
            }
        }

        if ($depth >= 4) {
            return null;
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->findBool($value, $keys, $depth + 1);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $keys
     */
    private function findDate(array $payload, array $keys, int $depth = 0): ?string
    {
        foreach ($payload as $key => $value) {
            if (!in_array(strtolower((string) $key), $keys, true)) {
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $normalized = $this->normalizeDate(trim($value));

                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        if ($depth >= 4) {
            return null;
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $nested = $this->findDate($value, $keys, $depth + 1);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    public function normalizeDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            if ($date === false || $date->format($format) !== $value) {
                continue;
            }

            return $date->format('Y-m-d');
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
