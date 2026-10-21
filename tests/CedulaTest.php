<?php

declare(strict_types=1);

// Autoloading
spl_autoload_register(function (string $class): void {
    $prefixes = ['App\\' => __DIR__ . '/../app/', 'Modules\\' => __DIR__ . '/../Modules/'];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
        }
    }
});

use Modules\Identity\Contracts\CedulaProvider;
use Modules\Identity\Contracts\HttpTransport;
use Modules\Identity\Dto\CedulaConfig;
use Modules\Identity\Dto\CedulaRecord;
use Modules\Identity\Providers\HttpCedulaProvider;
use Modules\Identity\Providers\SandboxCedulaProvider;
use Modules\Identity\Services\CedulaValidationService;
use Modules\Identity\Support\CedulaResponseMapper;

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAILED: $message\n";
        exit(1);
    }
    echo "PASSED: $message\n";
}

function assertFalse(bool $condition, string $message): void {
    assertTrue(!$condition, $message);
}

function assertThrows(callable $fn, string $expectedException, string $message): void {
    try {
        $fn();
        echo "FAILED (No exception thrown): $message\n";
        exit(1);
    } catch (\Throwable $e) {
        if ($e instanceof $expectedException) {
            echo "PASSED: $message (" . get_class($e) . ": " . $e->getMessage() . ")\n";
        } else {
            echo "FAILED (Unexpected exception " . get_class($e) . "): $message\n";
            exit(1);
        }
    }
}

/** Proveedor falso que cuenta las consultas realizadas. */
class FakeCedulaProvider implements CedulaProvider {
    public int $calls = 0;
    public function __construct(private ?CedulaRecord $record = null, private ?\Throwable $error = null) {
    }
    public function lookup(string $cedula): CedulaRecord {
        $this->calls++;
        if ($this->error !== null) throw $this->error;
        return $this->record ?? CedulaRecord::notFound('fake');
    }
}

/** Transporte HTTP falso para probar el proveedor HTTP sin red. */
class FakeHttpTransport implements HttpTransport {
    public array $lastRequest = [];
    public function __construct(private int $status, private string $body, private ?string $error = null) {
    }
    public function request(string $method, string $url, array $headers, ?array $jsonBody, int $timeout): array {
        $this->lastRequest = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $jsonBody,
            'timeout' => $timeout,
        ];
        return ['status' => $this->status, 'body' => $this->body, 'error' => $this->error];
    }
}

function config(array $overrides = []): CedulaConfig {
    return CedulaConfig::fromArray(array_merge([
        'provider' => 'sandbox',
        'url' => '',
        'method' => 'GET',
        'api_key' => '',
        'api_key_header' => 'x-api-key',
        'api_host' => '',
        'timeout' => 8,
        'document_type' => 'CC',
        'max_age' => 24,
        'max_age_company' => 0,
        'name_match' => true,
        'name_threshold' => 0.8,
        'fail_open' => false,
    ], $overrides));
}

echo "== Normalización y formato de la cédula ==\n";
assertTrue(
    CedulaValidationService::normalizeDocument('1.020.203.040') === '1020203040',
    'La cédula se normaliza quitando puntos y espacios.'
);
assertTrue(
    CedulaValidationService::normalizeDocument(' CC 1010203040 ') === '1010203040',
    'La cédula se normaliza quitando el prefijo CC y espacios.'
);
assertTrue(
    CedulaValidationService::normalizeDocument('cc-1010203040') === '1010203040',
    'La cédula se normaliza con prefijo en minúscula y guion.'
);

$service = new CedulaValidationService(new SandboxCedulaProvider(), config());
assertTrue($service->assertFormat('1.020.203.040') === '1020203040', 'El formato válido devuelve la cédula normalizada.');
assertThrows(
    fn () => $service->assertFormat('abc'),
    InvalidArgumentException::class,
    'Una cédula sin dígitos es rechazada.'
);
assertThrows(
    fn () => $service->assertFormat('12345'),
    InvalidArgumentException::class,
    'Una cédula demasiado corta es rechazada.'
);
assertThrows(
    fn () => $service->assertFormat('1234567890123'),
    InvalidArgumentException::class,
    'Una cédula demasiado larga es rechazada.'
);

echo "\n== Cálculo de edad y límite de 24 años ==\n";
$now = new DateTimeImmutable('2026-10-21');
assertTrue(
    CedulaValidationService::calculateAge('2004-03-12', $now) === 22,
    'La edad se calcula a partir de la fecha de nacimiento.'
);
assertTrue(
    CedulaValidationService::calculateAge('2002-10-21', $now) === 24,
    'Un cumpleaños hoy cuenta como 24 años.'
);
assertTrue(
    CedulaValidationService::calculateAge('2002-10-22', $now) === 23,
    'Un cumpleaños mañana todavía es menor de 24 años.'
);
assertThrows(
    fn () => $service->assertAge('2001-07-25', 24),
    InvalidArgumentException::class,
    'Una persona mayor de 24 años no puede registrarse.'
);
$service->assertAge('2002-10-21', 24);
echo "PASSED: Una persona de exactamente 24 años sí puede registrarse.\n";
$service->assertAge('1985-06-20', 0);
echo "PASSED: Con límite 0 el requisito de edad se desactiva.\n";

echo "\n== La edad se valida antes de consultar la API ==\n";
$provider = new FakeCedulaProvider(CedulaRecord::found('ANA MARIA GOMEZ LOPEZ', '2004-03-12', 22, 'fake'));
$guarded = new CedulaValidationService($provider, config());
assertThrows(
    fn () => $guarded->validate('1010203040', 'ANA MARIA GOMEZ LOPEZ', '1990-01-01', 'candidate'),
    InvalidArgumentException::class,
    'El registro se detiene por edad antes de consultar el registro civil.'
);
assertTrue($provider->calls === 0, 'La API de cédulas no se consulta cuando el requisito de edad falla.');

echo "\n== Coincidencia de nombres ==\n";
assertTrue(
    CedulaValidationService::nameSimilarity('ANA MARÍA GÓMEZ LÓPEZ', 'ANA MARIA GOMEZ LOPEZ') === 1.0,
    'Los nombres se comparan sin tildes.'
);
assertTrue(
    CedulaValidationService::nameSimilarity('Juan Carlos Perez Gomez', 'JUAN CARLOS PEREZ GOMEZ') === 1.0,
    'El orden de las palabras no afecta la comparación.'
);
assertTrue(
    CedulaValidationService::nameSimilarity('MARIA DE LOS ANGELES PEREZ', 'ANA MARIA GOMEZ LOPEZ') < 0.8,
    'Nombres diferentes quedan por debajo del umbral.'
);

echo "\n== Validación completa contra el registro ==\n";
$record = $guarded->validate('1010203040', 'ANA MARIA GOMEZ LOPEZ', '2004-03-12', 'candidate');
assertTrue($record->exists, 'Una cédula registrada devuelve un registro válido.');
assertTrue($provider->calls === 1, 'La cédula se consulta una sola vez al registro.');
assertThrows(
    fn () => $guarded->validate('1010203040', 'OTRO NOMBRE COMPLETAMENTE DISTINTO', '2004-03-12', 'candidate'),
    InvalidArgumentException::class,
    'El nombre que no coincide con la cédula es rechazado.'
);
assertThrows(
    fn () => $guarded->validate('1010203040', 'ANA MARIA GOMEZ LOPEZ', '2003-01-01', 'candidate'),
    InvalidArgumentException::class,
    'La fecha de nacimiento que no coincide con la cédula es rechazada.'
);
assertThrows(
    fn () => (new CedulaValidationService(new FakeCedulaProvider(), config()))
        ->validate('1010203040', 'ANA MARIA GOMEZ LOPEZ', '2004-03-12', 'candidate'),
    InvalidArgumentException::class,
    'Una cédula que no existe en el registro es rechazada.'
);

echo "\n== El simulador avisa que no es el registro civil ==\n";
try {
    (new CedulaValidationService(new SandboxCedulaProvider(), config()))
        ->validate('123456789', 'PERSONA CUALQUIERA', '2004-03-12', 'candidate');
    echo "FAILED: La cédula desconocida debió ser rechazada.\n";
    exit(1);
} catch (InvalidArgumentException $exception) {
    assertTrue(
        str_contains($exception->getMessage(), 'simulador'),
        'En modo sandbox el mensaje aclara que el registro es un simulador.'
    );
}

echo "\n== Límite de edad por tipo de cuenta ==\n";
$companyService = new CedulaValidationService(
    new FakeCedulaProvider(CedulaRecord::found('LAURA SOFIA MARTINEZ CRUZ', '1985-06-20', 41, 'fake')),
    config(['max_age' => 24, 'max_age_company' => 0])
);
$companyService->validate('1030405060', 'LAURA SOFIA MARTINEZ CRUZ', '1985-06-20', 'recruiter');
echo "PASSED: Una cuenta de empresa se registra sin límite de edad (configurable).\n";

$companyLimited = new CedulaValidationService(
    new FakeCedulaProvider(CedulaRecord::found('LAURA SOFIA MARTINEZ CRUZ', '1985-06-20', 41, 'fake')),
    config(['max_age_company' => 24])
);
assertThrows(
    fn () => $companyLimited->validate('1030405060', 'LAURA SOFIA MARTINEZ CRUZ', '1985-06-20', 'recruiter'),
    InvalidArgumentException::class,
    'Con límite configurado, el reclutador mayor de 24 años también es rechazado.'
);

echo "\n== Simulador local de cédulas ==\n";
$sandbox = new SandboxCedulaProvider();
$sandboxRecord = $sandbox->lookup('1010203040');
assertTrue($sandboxRecord->exists, 'El simulador encuentra las cédulas de prueba.');
assertTrue($sandboxRecord->fullName === 'ANA MARIA GOMEZ LOPEZ', 'El simulador devuelve el nombre registrado.');
assertFalse($sandbox->lookup('9999999999')->exists, 'El simulador rechaza cédulas no registradas.');

echo "\n== Proveedor HTTP (consumo real de API) ==\n";
$mapper = new CedulaResponseMapper();
$httpConfig = config([
    'provider' => 'http',
    'url' => 'https://api.ejemplo.com/cedula/{cedula}',
    'api_key' => 'clave-gratuita',
    'api_key_header' => 'x-api-key',
]);
$transport = new FakeHttpTransport(200, json_encode([
    'data' => [
        'nombre' => 'ANA MARIA GOMEZ LOPEZ',
        'fecha_nacimiento' => '2004-03-12',
        'edad' => 22,
        'existe' => true,
    ],
]));
$httpProvider = new HttpCedulaProvider($httpConfig, $transport, $mapper);
$httpRecord = $httpProvider->lookup('1020304050');
assertTrue($httpRecord->exists, 'La respuesta de la API se normaliza correctamente.');
assertTrue($httpRecord->fullName === 'ANA MARIA GOMEZ LOPEZ', 'La API devuelve el nombre del titular.');
assertTrue($httpRecord->birthDate === '2004-03-12', 'La API devuelve la fecha de nacimiento.');
assertTrue($transport->lastRequest['url'] === 'https://api.ejemplo.com/cedula/1020304050', 'La cédula se envía en la URL configurada.');
assertTrue(($transport->lastRequest['headers']['x-api-key'] ?? '') === 'clave-gratuita', 'La credencial se envía en el header configurado.');

$rapidConfig = config([
    'provider' => 'http',
    'url' => 'https://{host}/v1/cedula/{cedula}',
    'api_key' => 'clave-rapidapi',
    'api_key_header' => 'X-RapidAPI-Key',
    'api_host' => 'cedulas-colombia.p.rapidapi.com',
]);
$transport = new FakeHttpTransport(200, json_encode(['exists' => true, 'fullName' => 'LAURA SOFIA MARTINEZ CRUZ']));
$rapidProvider = new HttpCedulaProvider($rapidConfig, $transport, $mapper);
$rapidProvider->lookup('1030405060');
assertTrue(
    $transport->lastRequest['url'] === 'https://cedulas-colombia.p.rapidapi.com/v1/cedula/1030405060',
    'El marcador {host} se reemplaza por el host configurado (tipo RapidAPI).'
);
assertTrue(
    ($transport->lastRequest['headers']['X-RapidAPI-Key'] ?? '') === 'clave-rapidapi',
    'El header de credenciales es configurable (tipo RapidAPI).'
);
assertTrue(
    ($transport->lastRequest['headers']['X-RapidAPI-Host'] ?? '') === 'cedulas-colombia.p.rapidapi.com',
    'Se envía el header X-RapidAPI-Host cuando se configura el host.'
);

$transport = new FakeHttpTransport(404, json_encode(['message' => 'No encontrado']));
$httpProvider = new HttpCedulaProvider($httpConfig, $transport, $mapper);
assertFalse($httpProvider->lookup('1020304050')->exists, 'Una respuesta 404 significa cédula no registrada.');

$transport = new FakeHttpTransport(401, json_encode(['message' => 'Unauthorized']));
$httpProvider = new HttpCedulaProvider($httpConfig, $transport, $mapper);
assertThrows(
    fn () => $httpProvider->lookup('1020304050'),
    RuntimeException::class,
    'Credenciales inválidas producen un error controlado.'
);

$transport = new FakeHttpTransport(200, 'no es json');
$httpProvider = new HttpCedulaProvider($httpConfig, $transport, $mapper);
assertThrows(
    fn () => $httpProvider->lookup('1020304050'),
    RuntimeException::class,
    'Una respuesta ilegible produce un error controlado.'
);

$transport = new FakeHttpTransport(0, '', 'Connection timed out');
$httpProvider = new HttpCedulaProvider($httpConfig, $transport, $mapper);
assertThrows(
    fn () => $httpProvider->lookup('1020304050'),
    RuntimeException::class,
    'Una caída de red produce un error controlado.'
);

echo "\n== Normalización de respuestas de distintos proveedores ==\n";
assertTrue(
    $mapper->map(['existe' => false, 'nombre' => ''])->exists === false,
    'La respuesta con existe=false se interpreta como cédula no encontrada.'
);
assertTrue(
    $mapper->map(['found' => true, 'fullName' => 'JUAN CARLOS PEREZ GOMEZ', 'dateOfBirth' => '25/07/2001'])->birthDate === '2001-07-25',
    'Se normaliza la fecha de nacimiento en formato dd/mm/aaaa.'
);
assertTrue(
    $mapper->map(['status' => 'not_found'])->exists === false,
    'Un estado not_found se interpreta como cédula no encontrada.'
);
assertTrue(
    $mapper->map(['person' => ['name' => 'LAURA SOFIA MARTINEZ CRUZ']])->exists === true,
    'Se buscan los datos en sub-objetos de la respuesta.'
);

echo "\nTODAS LAS PRUEBAS DE CÉDULA PASARON\n";
