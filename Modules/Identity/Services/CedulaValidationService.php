<?php

namespace Modules\Identity\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Identity\Contracts\CedulaProvider;
use Modules\Identity\Dto\CedulaConfig;
use Modules\Identity\Dto\CedulaRecord;

/**
 * Reglas de negocio de la validación de cédula:
 *  1. Formato del documento.
 *  2. Edad máxima (se evalúa antes de consultar la API).
 *  3. Existencia de la cédula en el registro.
 *  4. Coincidencia de los datos capturados (nombre y fecha de nacimiento).
 */
final class CedulaValidationService
{
    private const DOCUMENT_PREFIXES = ['CC', 'CE', 'TI', 'RC', 'PA', 'PEP', 'NIT'];

    private const NAME_PARTICLES = [
        'DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'E', 'DA', 'DAS', 'DO', 'DOS',
        'MC', 'MAC', 'ME', 'DI', 'DIOS',
    ];

    public function __construct(
        private CedulaProvider $provider,
        private CedulaConfig $config
    ) {
    }

    /**
     * Deja el documento solo con dígitos, quitando prefijos y separadores.
     */
    public static function normalizeDocument(string $document): string
    {
        $cleaned = strtoupper(trim($document));
        $cleaned = preg_replace('/[^0-9A-Z]/', '', $cleaned) ?? '';

        foreach (self::DOCUMENT_PREFIXES as $prefix) {
            if (str_starts_with($cleaned, $prefix)) {
                $cleaned = substr($cleaned, strlen($prefix));
                break;
            }
        }

        return preg_replace('/\D/', '', $cleaned) ?? '';
    }

    /**
     * Valida el formato del documento y devuelve su versión normalizada.
     */
    public function assertFormat(string $document): string
    {
        $normalized = self::normalizeDocument($document);

        if ($normalized === '') {
            throw new InvalidArgumentException('Ingresa tu número de cédula para continuar.');
        }

        if (strlen($normalized) < 6 || strlen($normalized) > 10) {
            throw new InvalidArgumentException('La cédula debe tener entre 6 y 10 dígitos.');
        }

        return $normalized;
    }

    /**
     * Indica si el portal debe permitir el registro cuando la API no responde.
     */
    public function allowsFailOpen(): bool
    {
        return $this->config->failOpen;
    }

    /**
     * Calcula la edad en años a partir de la fecha de nacimiento.
     */
    public static function calculateAge(string $birthDate, ?DateTimeImmutable $now = null): int
    {
        $birthDate = trim($birthDate);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);

        if ($date === false || $date->format('Y-m-d') !== $birthDate) {
            throw new InvalidArgumentException('Ingresa una fecha de nacimiento válida.');
        }

        $reference = $now ?? new DateTimeImmutable('now');

        return (int) $date->diff($reference)->y;
    }

    /**
     * Rechaza el registro cuando la persona supera la edad máxima permitida.
     */
    public function assertAge(string $birthDate, int $maxAge): void
    {
        if ($maxAge <= 0) {
            return;
        }

        $age = self::calculateAge($birthDate);

        if ($age > $maxAge) {
            throw new InvalidArgumentException(
                'Esta plataforma es para jóvenes de hasta ' . $maxAge . ' años. '
                . 'La fecha de nacimiento ingresada indica ' . $age . ' años.'
            );
        }
    }

    /**
     * Ejecuta la validación completa de la cédula.
     *
     * @param string $role candidate | recruiter
     */
    public function validate(
        string $document,
        string $fullName,
        ?string $birthDate,
        string $role = 'candidate'
    ): CedulaRecord {
        $normalized = $this->assertFormat($document);
        $maxAge = $this->config->maxAgeFor($role);

        // La edad se evalúa antes de consultar el registro, tal como exige el proceso.
        if ($maxAge > 0) {
            if ($birthDate === null || trim($birthDate) === '') {
                throw new InvalidArgumentException('Ingresa tu fecha de nacimiento para validar la cédula.');
            }

            $this->assertAge($birthDate, $maxAge);
        }

        $record = $this->provider->lookup($normalized);

        if (!$record->exists) {
            if ($record->source === 'sandbox') {
                throw new InvalidArgumentException(
                    'La cédula no existe en el simulador de prueba del portal. '
                    . 'Para validar cédulas reales configura una API gratuita en el archivo .env '
                    . '(YEB_CEDULA_PROVIDER=http) o usa una de las cédulas de prueba documentadas en el README.'
                );
            }

            throw new InvalidArgumentException(
                'La cédula no se encuentra registrada en el registro civil. Verifica el número e inténtalo de nuevo.'
            );
        }

        $this->assertNameMatches($fullName, $record);
        $this->assertBirthDateMatches($birthDate, $record);

        return $record;
    }

    private function assertNameMatches(string $fullName, CedulaRecord $record): void
    {
        if (!$this->config->nameMatch) {
            return;
        }

        $registeredName = trim((string) $record->fullName);

        if ($registeredName === '') {
            return;
        }

        $similarity = self::nameSimilarity($fullName, $registeredName);

        if ($similarity < $this->config->nameThreshold) {
            throw new InvalidArgumentException(
                'El nombre capturado no coincide con el registrado en tu cédula. '
                . 'Revisa cómo escribiste tu nombre completo.'
            );
        }
    }

    private function assertBirthDateMatches(?string $birthDate, CedulaRecord $record): void
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return;
        }

        if ($record->birthDate !== null) {
            if ($record->birthDate !== trim($birthDate)) {
                throw new InvalidArgumentException(
                    'La fecha de nacimiento no coincide con la registrada en tu cédula.'
                );
            }

            return;
        }

        if ($record->age !== null) {
            $age = self::calculateAge($birthDate);

            if ($age !== $record->age) {
                throw new InvalidArgumentException(
                    'La fecha de nacimiento no coincide con la registrada en tu cédula.'
                );
            }
        }
    }

    /**
     * Compara dos nombres por tokens normalizados (sin tildes ni partículas).
     */
    public static function nameSimilarity(string $first, string $second): float
    {
        $tokensFirst = self::nameTokens($first);
        $tokensSecond = self::nameTokens($second);

        if ($tokensFirst === [] || $tokensSecond === []) {
            return 0.0;
        }

        $matches = count(array_intersect($tokensFirst, $tokensSecond));
        $total = max(count($tokensFirst), count($tokensSecond));

        return $total > 0 ? $matches / $total : 0.0;
    }

    /**
     * @return string[]
     */
    private static function nameTokens(string $name): array
    {
        $normalized = strtoupper(trim($name));
        $normalized = self::removeAccents($normalized);
        $normalized = preg_replace('/[^A-Z ]/', ' ', $normalized) ?? '';
        $tokens = preg_split('/\s+/', trim($normalized)) ?: [];

        return array_values(array_filter($tokens, static function (string $token): bool {
            return $token !== '' && !in_array($token, self::NAME_PARTICLES, true);
        }));
    }

    private static function removeAccents(string $value): string
    {
        $converted = strtr($value, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $converted);

            if (is_string($transliterated) && $transliterated !== '') {
                $converted = $transliterated;
            }
        }

        return preg_replace('/[^A-Za-z0-9 ]/', '', $converted) ?? $converted;
    }
}
