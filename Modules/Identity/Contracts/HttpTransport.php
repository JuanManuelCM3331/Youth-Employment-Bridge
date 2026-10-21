<?php

namespace Modules\Identity\Contracts;

/**
 * Contrato mínimo para ejecutar peticiones HTTP, permite sustituir cURL
 * por un doble de prueba sin depender de la red.
 */
interface HttpTransport
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $jsonBody
     * @return array{status: int, body: string, error: string|null}
     */
    public function request(string $method, string $url, array $headers, ?array $jsonBody, int $timeout): array;
}
