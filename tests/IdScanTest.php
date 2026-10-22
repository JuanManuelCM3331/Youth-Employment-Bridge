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

use Modules\Identity\Dto\IdScanConfig;
use Modules\Identity\Dto\ScannedId;
use Modules\Identity\Services\IdScanService;
use Modules\Identity\Services\IdScanStorage;

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

/** Fecha de nacimiento de alguien que cumple exactamente la edad indicada hoy. */
function birthDateForAge(int $age): string {
    return (new DateTimeImmutable('today'))->modify('-' . $age . ' years')->format('Y-m-d');
}

/** Payload tal como lo entrega el lector del navegador en el formulario. */
function scanPayload(array $overrides = []): array {
    return array_merge([
        'source' => 'pdf417',
        'firstName' => 'ANA',
        'middleName' => 'MARIA',
        'lastName' => 'GOMEZ',
        'secondLastName' => 'LOPEZ',
        'birthDate' => '12-03-2004',
        'birthDateISO' => '2004-03-12',
        'bloodType' => 'O+',
        'gender' => 'F',
        'expiryDateISO' => '2030-05-20',
        'documentInfo' => [
            'documentNumber' => '1020203040',
            'afisCode' => 'ABC123',
            'fingerCard' => 'XYZ789',
        ],
    ], $overrides);
}

function scanConfig(array $overrides = []): IdScanConfig {
    return IdScanConfig::fromArray(array_merge([
        'required' => true,
        'min_age' => 16,
        'max_age' => 24,
        'name_match' => true,
        'name_threshold' => 0.8,
        'max_size' => 8 * 1024 * 1024,
    ], $overrides));
}

echo "== Configuración por defecto ==\n";
$config = scanConfig();
assertTrue($config->required === true, 'Por defecto la foto del reverso es obligatoria.');
assertTrue($config->minAge === 16 && $config->maxAge === 24, 'El rango de edad por defecto es mayor a 16 y menor a 24.');
assertTrue(
    $config->ageRangeLabel() === 'más de 16 y menos de 24 años',
    'La etiqueta del rango de edad se redacta para la interfaz.'
);

echo "\n== Lectura del PDF417 ==\n";
$scan = ScannedId::fromPayload(scanPayload());
assertTrue($scan->documentNumber === '1020203040', 'Se lee el número de documento.');
assertTrue($scan->fullName() === 'ANA MARIA GOMEZ LOPEZ', 'Se arma el nombre completo con nombres y apellidos.');
assertTrue($scan->birthDate === '2004-03-12', 'Se normaliza la fecha de nacimiento a ISO.');
assertTrue($scan->source === 'pdf417', 'Se registra el origen de la lectura (PDF417).');
assertTrue($scan->bloodType === 'O+' && $scan->gender === 'F', 'Se leen el grupo sanguíneo y el sexo.');
assertTrue($scan->expiryDate === '2030-05-20', 'Se lee la fecha de vencimiento.');
assertTrue($scan->age() >= 22, 'La edad se calcula a partir de la fecha de nacimiento.');

$mrz = ScannedId::fromPayload(scanPayload([
    'source' => 'mrz',
    'documentInfo' => ['documentNumber' => '1020203040'],
]));
assertTrue($mrz->source === 'mrz', 'También se acepta una lectura coming del MRZ.');

echo "\n== Datos incompletos o inválidos ==\n";
assertThrows(
    fn () => ScannedId::fromPayload(scanPayload(['documentInfo' => ['documentNumber' => '']])),
    InvalidArgumentException::class,
    'Una lectura sin número de documento es rechazada.'
);
assertThrows(
    fn () => ScannedId::fromPayload(scanPayload(['birthDateISO' => ''])),
    InvalidArgumentException::class,
    'Una lectura sin fecha de nacimiento es rechazada.'
);
assertThrows(
    fn () => ScannedId::fromPayload(scanPayload(['birthDateISO' => '2004-02-31'])),
    InvalidArgumentException::class,
    'Una fecha de nacimiento inexistente es rechazada.'
);
assertThrows(
    fn () => ScannedId::fromPayload(scanPayload(['documentInfo' => ['documentNumber' => 'ABC']])),
    InvalidArgumentException::class,
    'Un número de documento no numérico es rechazado.'
);

echo "\n== Rango de edad (mayor a 16 y menor a 24) ==\n";
$service = new IdScanService(scanConfig());

$tooYoung = ScannedId::fromPayload(scanPayload(['birthDateISO' => birthDateForAge(16)]));
assertThrows(
    fn () => $service->assertAge($tooYoung),
    InvalidArgumentException::class,
    'Con 16 años cumplidos el registro es rechazado.'
);

$justAllowed = ScannedId::fromPayload(scanPayload(['birthDateISO' => birthDateForAge(17)]));
$service->assertAge($justAllowed);
echo "PASSED: Con 17 años cumplidos el registro es aceptado.\n";

$oldestAllowed = ScannedId::fromPayload(scanPayload(['birthDateISO' => birthDateForAge(23)]));
$service->assertAge($oldestAllowed);
echo "PASSED: Con 23 años cumplidos el registro es aceptado.\n";

$tooOld = ScannedId::fromPayload(scanPayload(['birthDateISO' => birthDateForAge(24)]));
assertThrows(
    fn () => $service->assertAge($tooOld),
    InvalidArgumentException::class,
    'Con 24 años cumplidos el registro es rechazado.'
);

assertThrows(
    fn () => $service->assertAge(ScannedId::fromPayload(scanPayload(['birthDateISO' => birthDateForAge(40)]))),
    InvalidArgumentException::class,
    'Una persona de 40 años no puede registrarse como candidata.'
);

echo "\n== Validación completa de la lectura ==\n";
$valid = $service->validate(
    scanPayload(),
    'ANA MARIA GOMEZ LOPEZ',
    '1020203040',
    '2004-03-12'
);
assertTrue($valid->documentNumber === '1020203040', 'Una lectura coherente con el formulario se valida.');

$service->validate(
    scanPayload(['birthDateISO' => birthDateForAge(20), 'birthDate' => birthDateForAge(20)]),
    'ANA MARIA GOMEZ LOPEZ',
    '1020203040'
);
echo "PASSED: La fecha de nacimiento puede omitirse en el formulario.\n";

assertThrows(
    fn () => $service->validate(scanPayload(), 'ANA MARIA GOMEZ LOPEZ', '9999999999'),
    InvalidArgumentException::class,
    'El número de documento que no coincide con el leído es rechazado.'
);
assertThrows(
    fn () => $service->validate(scanPayload(), 'OTRO NOMBRE COMPLETAMENTE DISTINTO', '1020203040'),
    InvalidArgumentException::class,
    'El nombre que no coincide con el del documento es rechazado.'
);
assertThrows(
    fn () => $service->validate(scanPayload(), 'ANA MARIA GOMEZ LOPEZ', '1020203040', '1999-01-01'),
    InvalidArgumentException::class,
    'La fecha de nacimiento que no coincide con la del documento es rechazada.'
);
assertThrows(
    fn () => $service->validate(
        scanPayload(['birthDateISO' => birthDateForAge(30), 'birthDate' => birthDateForAge(30)]),
        'ANA MARIA GOMEZ LOPEZ',
        '1020203040'
    ),
    InvalidArgumentException::class,
    'La validación completa también rechaza la edad fuera del rango.'
);

echo "\n== El número capturado puede venir con separadores ==\n";
$service->validate(scanPayload(), 'ANA MARIA GOMEZ LOPEZ', '1.020.203.040');
echo "PASSED: El documento capturado con puntos se normaliza antes de comparar.\n";

echo "\n== La coincidencia de nombres se puede desactivar ==\n";
$relaxed = new IdScanService(scanConfig(['name_match' => false]));
$relaxed->validate(scanPayload(), 'OTRO NOMBRE COMPLETAMENTE DISTINTO', '1020203040');
echo "PASSED: Con la validación de nombre desactivada el registro continúa.\n";

echo "\n== La foto del reverso puede ser obligatoria o no ==\n";
assertTrue($service->isRequired() === true, 'Con required=true la foto del reverso es obligatoria.');
assertTrue(
    (new IdScanService(scanConfig(['required' => false])))->isRequired() === false,
    'Con required=false la foto del reverso es opcional.'
);

echo "\n== Almacenamiento privado de la imagen ==\n";
$storage = new IdScanStorage(scanConfig());

// PNG 1x1 transparente.
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$stored = $storage->storeEncodedImage($png, ScannedId::fromPayload(scanPayload()));

assertTrue(is_file($stored['path']), 'La imagen del documento queda guardada en el almacenamiento privado.');
assertTrue($stored['mime'] === 'image/png', 'Se registra el tipo real de la imagen.');
assertTrue($stored['document'] === '1020203040', 'Se guarda el número de documento de la lectura.');
assertTrue($stored['source'] === 'pdf417', 'Se guarda el origen de la lectura.');
assertTrue(str_contains($stored['path'], 'storage/id-scans'), 'La imagen se guarda dentro de storage/id-scans.');
assertTrue(strpos(basename($stored['path']), '1020203040') === false, 'El archivo se guarda con un nombre aleatorio.');

$recordPath = dirname($stored['path']) . '/' . pathinfo($stored['path'], PATHINFO_FILENAME) . '.json';
assertTrue(is_file($recordPath), 'Junto a la imagen se escribe el registro JSON de la lectura.');
$record = json_decode((string) file_get_contents($recordPath), true);
assertTrue($record['documentNumber'] === '1020203040', 'El registro JSON guarda el número de documento.');
assertTrue($record['birthDate'] === '2004-03-12', 'El registro JSON guarda la fecha de nacimiento.');
assertTrue(isset($record['createdAt']), 'El registro JSON guarda la fecha de creación.');

$read = $storage->read($stored['path']);
assertTrue($read['path'] === realpath($stored['path']), 'La imagen se puede volver a leer desde el almacenamiento.');
assertTrue($read['mime'] === 'image/png', 'La lectura devuelve el tipo de la imagen.');

assertThrows(
    fn () => $storage->read(__DIR__ . '/../config/id_scan.php'),
    InvalidArgumentException::class,
    'No se puede leer un archivo que está fuera del almacenamiento privado.'
);

echo "\n== Imágenes no permitidas ==\n";
assertThrows(
    fn () => $storage->storeEncodedImage(base64_encode('esto no es una imagen'), ScannedId::fromPayload(scanPayload())),
    InvalidArgumentException::class,
    'Un archivo que no es una imagen es rechazado.'
);

@unlink($stored['path']);
@unlink($recordPath);

echo "\nTODAS LAS PRUEBAS DE LECTURA DE DOCUMENTO PASARON\n";
