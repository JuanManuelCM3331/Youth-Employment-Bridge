<?php

namespace Modules\Identity\Dto;

/**
 * Configuración de la lectura y validación del reverso del documento de identidad.
 *
 * El registro del candidato se valida con los datos leídos del PDF417 (o del
 * MRZ) de la cédula o de la tarjeta de identidad, sin consultar el registro civil.
 */
final class IdScanConfig
{
    public function __construct(
        public readonly bool $required = true,
        public readonly int $minAge = 16,
        public readonly int $maxAge = 24,
        public readonly bool $nameMatch = true,
        public readonly float $nameThreshold = 0.8,
        public readonly int $maxSize = 8388608
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            required: (bool) ($config['required'] ?? true),
            minAge: (int) ($config['min_age'] ?? 16),
            maxAge: (int) ($config['max_age'] ?? 24),
            nameMatch: (bool) ($config['name_match'] ?? true),
            nameThreshold: (float) ($config['name_threshold'] ?? 0.8),
            maxSize: (int) ($config['max_size'] ?? 8 * 1024 * 1024)
        );
    }

    /**
     * Rango de edad permitido, redactado para los mensajes de la interfaz.
     */
    public function ageRangeLabel(): string
    {
        return 'más de ' . $this->minAge . ' y menos de ' . $this->maxAge . ' años';
    }
}
