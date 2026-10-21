<?php

namespace Modules\Identity\Clients;

use Modules\Identity\Contracts\HttpTransport;

/**
 * Transporte HTTP basado en cURL con respaldo en el wrapper de streams.
 */
final class CurlHttpTransport implements HttpTransport
{
    public function request(string $method, string $url, array $headers, ?array $jsonBody, int $timeout): array
    {
        $method = strtoupper($method);
        $payload = $jsonBody !== null ? json_encode($jsonBody, JSON_UNESCAPED_UNICODE) : null;
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        if ($payload !== null) {
            $headerLines[] = 'Content-Type: application/json';
        }

        if (function_exists('curl_init')) {
            $handle = curl_init($url);

            if ($handle !== false) {
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => $headerLines,
                    CURLOPT_CONNECTTIMEOUT => $timeout,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                ]);

                if ($payload !== null) {
                    curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
                }

                $body = curl_exec($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $error = curl_errno($handle) !== 0 ? curl_error($handle) : null;
                curl_close($handle);

                return [
                    'status' => $status,
                    'body' => is_string($body) ? $body : '',
                    'error' => $error,
                ];
            }
        }

        return $this->streamRequest($method, $url, $headerLines, $payload, $timeout);
    }

    private function streamRequest(string $method, string $url, array $headerLines, ?string $payload, int $timeout): array
    {
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ];

        if ($payload !== null) {
            $options['http']['content'] = $payload;
        }

        $context = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);
        $status = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => $body === false ? 'No se pudo establecer la conexión con la API de cédulas.' : null,
        ];
    }
}
