<?php

use App\Core\Env;

Env::load(__DIR__ . '/../.env');

return [
    // Exigir la foto del reverso del documento para crear la cuenta de candidato.
    'required' => Env::bool('YEB_ID_SCAN_REQUIRED', true),
    // Edad mínima (excluyente): la edad debe ser MAYOR a este valor.
    'min_age' => Env::int('YEB_ID_SCAN_MIN_AGE', 16),
    // Edad máxima (excluyente): la edad debe ser MENOR a este valor.
    'max_age' => Env::int('YEB_ID_SCAN_MAX_AGE', 24),
    // Verificar que el nombre capturado coincida con el leído en el documento.
    'name_match' => Env::bool('YEB_ID_SCAN_NAME_MATCH', true),
    // Umbral de coincidencia de nombres (0 - 1).
    'name_threshold' => Env::float('YEB_ID_SCAN_NAME_THRESHOLD', 0.8),
    // Peso máximo de la imagen del reverso en bytes.
    'max_size' => Env::int('YEB_ID_SCAN_MAX_SIZE', 8 * 1024 * 1024),
];
