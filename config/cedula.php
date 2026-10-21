<?php

use App\Core\Env;

Env::load(__DIR__ . '/../.env');

return [
    // Proveedor de la consulta de cédula: sandbox | http
    'provider' => Env::get('YEB_CEDULA_PROVIDER', 'sandbox'),
    // Plantilla de URL del endpoint. Soporta el marcador {cedula}.
    'url' => Env::get('YEB_CEDULA_API_URL', ''),
    // Método HTTP de la consulta: GET | POST
    'method' => strtoupper(Env::get('YEB_CEDULA_API_METHOD', 'GET')),
    // Credencial de la API (gratuita en los planes gratuitos de los proveedores).
    'api_key' => Env::get('YEB_CEDULA_API_KEY', ''),
    // Nombre del header donde se envía la credencial (x-api-key, apikey, Authorization...).
    'api_key_header' => Env::get('YEB_CEDULA_API_KEY_HEADER', 'x-api-key'),
    // Host adicional para proveedores tipo RapidAPI (X-RapidAPI-Host).
    'api_host' => Env::get('YEB_CEDULA_API_HOST', ''),
    // Tiempo máximo de espera de la consulta en segundos.
    'timeout' => Env::int('YEB_CEDULA_API_TIMEOUT', 8),
    // Tipo de documento consultado en la API (CC, CE, TI...).
    'document_type' => Env::get('YEB_CEDULA_DOCUMENT_TYPE', 'CC'),
    // Edad máxima para crear una cuenta de candidato (0 = sin límite).
    'max_age' => Env::int('YEB_CEDULA_MAX_AGE', 24),
    // Edad máxima para crear una cuenta de empresa (0 = sin límite).
    'max_age_company' => Env::int('YEB_CEDULA_MAX_AGE_COMPANY', 0),
    // Validar que el nombre capturado coincida con el registrado en la cédula.
    'name_match' => Env::bool('YEB_CEDULA_NAME_MATCH', true),
    // Umbral de coincidencia de nombres (0 - 1).
    'name_threshold' => Env::float('YEB_CEDULA_NAME_THRESHOLD', 0.8),
    // Si la API falla, permitir el registro (1) o bloquearlo (0).
    'fail_open' => Env::bool('YEB_CEDULA_FAIL_OPEN', false),
];
