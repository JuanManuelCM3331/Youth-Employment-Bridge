<?php

namespace Modules\Identity\Dto;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Datos leídos del reverso de una cédula de ciudadanía o de una tarjeta de identidad.
 *
 * El lector del navegador (PDF417 y, como respaldo, la zona MRZ) entrega un
 * objeto JSON. Esta clase normaliza ese objeto y descarta cualquier dato que
 * no cumpla el formato esperado antes de usarlo en el registro.
 */
final class ScannedId
{
    private const CLEAN_PATTERN = '/[^\p{L}\p{N} \-+\/]/u';

    public function __construct(
        public readonly string $documentNumber,
        public readonly string $firstName,
        public readonly string $middleName,
        public readonly string $lastName,
        public readonly string $secondLastName,
        public readonly string $birthDate,
        public readonly string $gender,
        public readonly string $bloodType,
        public readonly string $expiryDate,
        public readonly string $source,
        public readonly array $raw = []
    ) {
    }

    /**
     * Construye el registro a partir del JSON que envía el lector del navegador.
     *
     * @throws InvalidArgumentException cuando falta el documento o la fecha de nacimiento.
     */
    public static function fromPayload(array $payload): self
    {
        $documentInfo = is_array($payload['documentInfo'] ?? null) ? $payload['documentInfo'] : [];
        $documentNumber = preg_replace('/\D/', '', (string) ($documentInfo['documentNumber'] ?? '')) ?? '';

        if (!preg_match('/^\d{4,12}$/', $documentNumber)) {
            throw new InvalidArgumentException(
                'No se pudo leer el número de documento del reverso. '
                . 'Repite la foto con el código de barras completamente visible.'
            );
        }

        $birthDate = self::isoDate($payload['birthDateISO'] ?? '');

        if ($birthDate === '') {
            throw new InvalidArgumentException(
                'No se pudo leer la fecha de nacimiento del documento. '
                . 'Repite la foto con el código de barras completamente visible.'
            );
        }

        $source = in_array($payload['source'] ?? '', ['pdf417', 'mrz'], true)
            ? (string) $payload['source']
            : 'pdf417';

        return new self(
            $documentNumber,
            self::clean($payload['firstName'] ?? ''),
            self::clean($payload['middleName'] ?? ''),
            self::clean($payload['lastName'] ?? ''),
            self::clean($payload['secondLastName'] ?? ''),
            $birthDate,
            self::clean($payload['gender'] ?? '', 1),
            self::clean($payload['bloodType'] ?? '', 3),
            self::isoDate($payload['expiryDateISO'] ?? ''),
            $source,
            $payload
        );
    }

    /**
     * Nombre completo tal como aparece en el documento (nombres y apellidos).
     */
    public function fullName(): string
    {
        $parts = array_filter(
            [$this->firstName, $this->middleName, $this->lastName, $this->secondLastName],
            static fn (string $part): bool => trim($part) !== ''
        );

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
    }

    /**
     * Edad en años cumplidos al momento de la validación.
     */
    public function age(?DateTimeImmutable $now = null): int
    {
        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $this->birthDate);

        if ($birthDate === false) {
            throw new InvalidArgumentException('La fecha de nacimiento leída no es válida.');
        }

        $reference = $now ?? new DateTimeImmutable('now');

        return (int) $birthDate->diff($reference)->y;
    }

    /**
     * Registro listo para persistir (mismo formato del lector del navegador).
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'documentNumber' => $this->documentNumber,
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'lastName' => $this->lastName,
            'secondLastName' => $this->secondLastName,
            'fullName' => $this->fullName(),
            'birthDate' => $this->birthDate,
            'gender' => $this->gender,
            'bloodType' => $this->bloodType,
            'expiryDate' => $this->expiryDate,
        ];
    }

    /**
     * Quita caracteres no válidos y recorta el texto a un largo máximo.
     */
    private static function clean(mixed $value, int $max = 60): string
    {
        $cleaned = preg_replace(self::CLEAN_PATTERN, '', (string) $value) ?? '';

        return mb_substr($cleaned, 0, $max);
    }

    /**
     * Acepta solo fechas ISO (AAAA-MM-DD) reales.
     */
    private static function isoDate(mixed $value): string
    {
        $date = trim((string) $value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return '';
        }

        return $date;
    }
}
