<?php

namespace Modules\Identity\Providers;

use DateTimeImmutable;
use Modules\Identity\Contracts\CedulaProvider;
use Modules\Identity\Dto\CedulaRecord;

/**
 * Simulador local del registro de cédulas.
 *
 * Permite desarrollar y probar el flujo completo de registro sin costo
 * ni credenciales. Los datos son ficticios y NO reemplazan la consulta real
 * contra el registro civil: para producción se debe configurar el proveedor
 * http (YEB_CEDULA_PROVIDER=http) apuntando a una API real.
 */
final class SandboxCedulaProvider implements CedulaProvider
{
    private const REGISTRY = [
        '1010203040' => ['nombre' => 'ANA MARIA GOMEZ LOPEZ', 'nacimiento' => '2004-03-12'],
        '1020304050' => ['nombre' => 'JUAN CARLOS PEREZ GOMEZ', 'nacimiento' => '2001-07-25'],
        '1030405060' => ['nombre' => 'LAURA SOFIA MARTINEZ CRUZ', 'nacimiento' => '2003-11-08'],
        '1040506070' => ['nombre' => 'DAVID ANDRES RAMIREZ ORTIZ', 'nacimiento' => '2005-05-19'],
        '1050607080' => ['nombre' => 'MARIA FERNANDA LOPEZ SANCHEZ', 'nacimiento' => '1999-02-14'],
        '1060708090' => ['nombre' => 'CARLOS ANDRES RUIZ MARTINEZ', 'nacimiento' => '1985-06-20'],
        '1070809100' => ['nombre' => 'MARIA JOSEFA RAMIREZ SOTO', 'nacimiento' => '2006-01-30'],
        '1080910110' => ['nombre' => 'ANDRES FERNANDO LOPEZ HERRERA', 'nacimiento' => '2003-06-18'],
        '1099887766' => ['nombre' => 'MATEO GOMEZ SUAREZ', 'nacimiento' => '2009-04-15'],
    ];

    public function lookup(string $cedula): CedulaRecord
    {
        $record = self::REGISTRY[$cedula] ?? null;

        if ($record === null) {
            return CedulaRecord::notFound('sandbox');
        }

        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $record['nacimiento']);

        return CedulaRecord::found(
            $record['nombre'],
            $record['nacimiento'],
            $birthDate === false ? null : $this->age($birthDate),
            'sandbox'
        );
    }

    /**
     * Cédulas disponibles en el simulador (solo datos ficticios de prueba).
     *
     * @return string[]
     */
    public static function availableDocuments(): array
    {
        return array_keys(self::REGISTRY);
    }

    /**
     * Detalle de las cédulas de prueba del simulador.
     *
     * @return array<string, array{nombre: string, nacimiento: string}>
     */
    public static function sampleRecords(): array
    {
        return self::REGISTRY;
    }

    private function age(DateTimeImmutable $birthDate): int
    {
        $now = new DateTimeImmutable('now');

        return (int) $birthDate->diff($now)->y;
    }
}
