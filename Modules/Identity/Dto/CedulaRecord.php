<?php

namespace Modules\Identity\Dto;

/**
 * Resultado normalizado de la consulta de una cédula.
 */
final class CedulaRecord
{
    public function __construct(
        public readonly bool $exists,
        public readonly ?string $fullName = null,
        public readonly ?string $birthDate = null,
        public readonly ?int $age = null,
        public readonly string $source = 'sandbox',
        public readonly array $raw = []
    ) {
    }

    public static function notFound(string $source = 'sandbox'): self
    {
        return new self(false, null, null, null, $source);
    }

    public static function found(
        string $fullName,
        ?string $birthDate = null,
        ?int $age = null,
        string $source = 'sandbox',
        array $raw = []
    ): self {
        return new self(true, $fullName, $birthDate, $age, $source, $raw);
    }
}
