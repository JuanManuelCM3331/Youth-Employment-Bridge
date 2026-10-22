<?php

namespace Modules\Identity\Services;

use InvalidArgumentException;
use Modules\Identity\Dto\IdScanConfig;
use Modules\Identity\Dto\ScannedId;

/**
 * Reglas de negocio de la verificación de identidad por lectura del documento.
 *
 * A diferencia de la consulta al registro civil, aquí los datos provienen del
 * propio documento que la persona adjunta: el número de documento, el nombre y
 * la fecha de nacimiento se leen del PDF417 (o del MRZ) y se validan contra lo
 * que la persona capturó en el formulario.
 */
final class IdScanService
{
    public function __construct(private IdScanConfig $config)
    {
    }

    /**
     * Indica si el portal exige la foto del reverso para crear la cuenta.
     */
    public function isRequired(): bool
    {
        return $this->config->required;
    }

    /**
     * Valida el documento leído contra los datos capturados en el formulario.
     *
     * @param array $payload JSON enviado por el lector del navegador.
     * @param string $typedName Nombre completo capturado en el formulario.
     * @param string $typedDocument Número de documento capturado en el formulario.
     * @param string|null $typedBirthDate Fecha de nacimiento capturada (opcional).
     */
    public function validate(
        array $payload,
        string $typedName,
        string $typedDocument,
        ?string $typedBirthDate = null
    ): ScannedId {
        $scan = ScannedId::fromPayload($payload);

        $this->assertAge($scan);
        $this->assertDocumentMatches($scan, $typedDocument);
        $this->assertNameMatches($scan, $typedName);
        $this->assertBirthDateMatches($scan, $typedBirthDate);

        return $scan;
    }

    /**
     * Exige una edad dentro del rango permitido por el portal.
     *
     * Los límites son excluyentes: la edad debe ser MAYOR que el mínimo
     * (16 años) y MENOR que el máximo (24 años).
     */
    public function assertAge(ScannedId $scan): void
    {
        $age = $scan->age();

        if ($this->config->minAge > 0 && $age <= $this->config->minAge) {
            throw new InvalidArgumentException(
                'Esta plataforma es para jóvenes de ' . $this->config->ageRangeLabel() . '. '
                . 'El documento que adjuntaste indica ' . $age . ' años.'
            );
        }

        if ($this->config->maxAge > 0 && $age >= $this->config->maxAge) {
            throw new InvalidArgumentException(
                'Esta plataforma es para jóvenes de ' . $this->config->ageRangeLabel() . '. '
                . 'El documento que adjuntaste indica ' . $age . ' años.'
            );
        }
    }

    /**
     * El documento leído debe ser el mismo que capturó la persona.
     */
    public function assertDocumentMatches(ScannedId $scan, string $typedDocument): void
    {
        $normalized = preg_replace('/\D/', '', $typedDocument) ?? '';

        if ($normalized === '' || $normalized !== $scan->documentNumber) {
            throw new InvalidArgumentException(
                'El número de documento capturado no coincide con el leído en tu documento. '
                . 'Revisa el número que escribiste o vuelve a subir la foto.'
            );
        }
    }

    /**
     * El nombre capturado debe coincidir con el nombre del documento.
     */
    public function assertNameMatches(ScannedId $scan, string $typedName): void
    {
        if (!$this->config->nameMatch) {
            return;
        }

        $documentName = $scan->fullName();

        if ($documentName === '') {
            return;
        }

        $similarity = CedulaValidationService::nameSimilarity($typedName, $documentName);

        if ($similarity < $this->config->nameThreshold) {
            throw new InvalidArgumentException(
                'El nombre capturado no coincide con el nombre de tu documento. '
                . 'Revisa cómo escribiste tu nombre completo.'
            );
        }
    }

    /**
     * Si la persona capturó una fecha de nacimiento, debe coincidir con la leída.
     */
    public function assertBirthDateMatches(ScannedId $scan, ?string $typedBirthDate): void
    {
        if ($typedBirthDate === null || trim($typedBirthDate) === '') {
            return;
        }

        if (trim($typedBirthDate) !== $scan->birthDate) {
            throw new InvalidArgumentException(
                'La fecha de nacimiento capturada no coincide con la leída en tu documento.'
            );
        }
    }
}
