<?php

namespace Modules\Identity\Providers;

use Modules\Identity\Contracts\CedulaProvider;
use Modules\Identity\Contracts\HttpTransport;
use Modules\Identity\Dto\CedulaConfig;
use Modules\Identity\Dto\CedulaRecord;
use Modules\Identity\Support\CedulaResponseMapper;
use RuntimeException;

/**
 * Proveedor que consulta una API externa de validación de cédulas.
 *
 * La URL se configura con la plantilla YEB_CEDULA_API_URL, por ejemplo:
 *   https://api.ejemplo.com/cedula/{cedula}
 * Si la plantilla no incluye el marcador, el número se agrega como query string.
 */
final class HttpCedulaProvider implements CedulaProvider
{
    public function __construct(
        private CedulaConfig $config,
        private HttpTransport $transport,
        private CedulaResponseMapper $mapper
    ) {
    }

    public function lookup(string $cedula): CedulaRecord
    {
        if (trim($this->config->url) === '') {
            throw new RuntimeException('No se configuró la URL de la API de cédulas (YEB_CEDULA_API_URL).');
        }

        $response = $this->transport->request(
            $this->config->method,
            $this->buildUrl($cedula),
            $this->buildHeaders(),
            $this->buildBody($cedula),
            $this->config->timeout
        );

        if ($response['error'] !== null) {
            throw new RuntimeException('No fue posible consultar la API de cédulas: ' . $response['error']);
        }

        $status = $response['status'];

        if ($status === 404) {
            return CedulaRecord::notFound('http');
        }

        if ($status === 401 || $status === 403) {
            throw new RuntimeException('Las credenciales de la API de cédulas no fueron aceptadas.');
        }

        if ($status === 429) {
            throw new RuntimeException('La API de cédulas está saturada. Intenta de nuevo en unos minutos.');
        }

        if ($status >= 500) {
            throw new RuntimeException('La API de cédulas no está disponible en este momento.');
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('La API de cédulas respondió con un estado inesperado (' . $status . ').');
        }

        $payload = json_decode($response['body'], true);

        if (!is_array($payload)) {
            throw new RuntimeException('La API de cédulas devolvió una respuesta que no pudo interpretarse.');
        }

        return $this->mapper->map($payload, 'http');
    }

    private function buildUrl(string $cedula): string
    {
        $template = $this->config->url;

        if ($this->config->apiHost !== '') {
            $template = str_replace('{host}', $this->config->apiHost, $template);
        }

        if (str_contains($template, '{cedula}')) {
            return str_replace('{cedula}', rawurlencode($cedula), $template);
        }

        $separator = str_contains($template, '?') ? '&' : '?';

        return $template . $separator . 'cedula=' . rawurlencode($cedula)
            . '&documento=' . rawurlencode($cedula)
            . '&docNumber=' . rawurlencode($cedula);
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'YEB-Portal/1.0',
        ];

        if ($this->config->apiKey !== '') {
            $headers[$this->config->apiKeyHeader] = $this->config->apiKey;
        }

        if ($this->config->apiHost !== '') {
            $headers['X-RapidAPI-Host'] = $this->config->apiHost;
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBody(string $cedula): ?array
    {
        if ($this->config->method !== 'POST') {
            return null;
        }

        return [
            'cedula' => $cedula,
            'documento' => $cedula,
            'docNumber' => $cedula,
            'numero' => $cedula,
            'docType' => $this->config->documentType,
            'tipoDocumento' => $this->config->documentType,
        ];
    }
}
