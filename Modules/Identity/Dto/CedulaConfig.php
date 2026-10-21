<?php

namespace Modules\Identity\Dto;

/**
 * Configuración de la integración con la API de cédulas.
 */
final class CedulaConfig
{
    public function __construct(
        public readonly string $provider = 'sandbox',
        public readonly string $url = '',
        public readonly string $method = 'GET',
        public readonly string $apiKey = '',
        public readonly string $apiKeyHeader = 'x-api-key',
        public readonly string $apiHost = '',
        public readonly int $timeout = 8,
        public readonly string $documentType = 'CC',
        public readonly int $maxAge = 24,
        public readonly int $maxAgeCompany = 0,
        public readonly bool $nameMatch = true,
        public readonly float $nameThreshold = 0.8,
        public readonly bool $failOpen = false
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            provider: (string) ($config['provider'] ?? 'sandbox'),
            url: (string) ($config['url'] ?? ''),
            method: strtoupper((string) ($config['method'] ?? 'GET')),
            apiKey: (string) ($config['api_key'] ?? ''),
            apiKeyHeader: (string) ($config['api_key_header'] ?? 'x-api-key'),
            apiHost: (string) ($config['api_host'] ?? ''),
            timeout: (int) ($config['timeout'] ?? 8),
            documentType: (string) ($config['document_type'] ?? 'CC'),
            maxAge: (int) ($config['max_age'] ?? 24),
            maxAgeCompany: (int) ($config['max_age_company'] ?? 0),
            nameMatch: (bool) ($config['name_match'] ?? true),
            nameThreshold: (float) ($config['name_threshold'] ?? 0.8),
            failOpen: (bool) ($config['fail_open'] ?? false)
        );
    }

    /**
     * Límite de edad aplicable al tipo de cuenta (candidate | recruiter).
     */
    public function maxAgeFor(string $role): int
    {
        return $role === 'recruiter' ? $this->maxAgeCompany : $this->maxAge;
    }
}
