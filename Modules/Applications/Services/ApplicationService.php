<?php

namespace Modules\Applications\Services;

use Modules\Applications\Repositories\ApplicationRepository;

final class ApplicationService
{
    /**
     * Reglas de transición de estados de una postulación:
     * - pending   -> review, interview, accepted, rejected
     * - review    -> interview, accepted, rejected
     * - interview -> accepted, rejected
     * - accepted  -> (estado final)
     * - rejected  -> (estado final)
     */
    public const ALLOWED_TRANSITIONS = [
        'pending'   => ['review', 'interview', 'accepted', 'rejected'],
        'review'    => ['interview', 'accepted', 'rejected'],
        'interview' => ['accepted', 'rejected'],
        'accepted'  => [],
        'rejected'  => [],
    ];

    private const STATUS_LABELS = [
        'pending'   => 'Pendiente',
        'review'    => 'En revisión',
        'interview' => 'Entrevista',
        'accepted'  => 'Aceptado',
        'rejected'  => 'Rechazado',
    ];

    public function __construct(private ApplicationRepository $repository)
    {
    }

    public function apply(int $jobId, int $userId): void
    {
        if ($this->repository->exists($jobId, $userId)) {
            throw new \InvalidArgumentException('Ya te postulaste a esta vacante.');
        }

        $status = $this->repository->jobStatus($jobId);

        if ($status !== 'published') {
            throw new \InvalidArgumentException('Esta vacante no está disponible para postulaciones.');
        }

        $this->repository->apply($jobId, $userId);
    }

    public function markInReview(int $candidateId, int $companyId, ?int $applicationId = null): array
    {
        return $this->repository->markInReview($candidateId, $companyId, $applicationId);
    }

    public function updateStatus(int $applicationId, int $companyId, string $status): bool
    {
        if (!array_key_exists($status, self::ALLOWED_TRANSITIONS)) {
            throw new \InvalidArgumentException('Estado de postulación inválido.');
        }

        $currentStatus = $this->repository->currentStatusForCompany($applicationId, $companyId);
        if ($currentStatus === null) {
            throw new \RuntimeException('No tienes permisos para actualizar esta postulación o la postulación no existe.');
        }

        if ($currentStatus === $status) {
            throw new \InvalidArgumentException('La postulación ya se encuentra en el estado seleccionado.');
        }

        $allowedNext = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($status, $allowedNext, true)) {
            $fromLabel = self::STATUS_LABELS[$currentStatus] ?? $currentStatus;
            $toLabel = self::STATUS_LABELS[$status] ?? $status;
            throw new \DomainException(
                sprintf('Transición de estado no permitida: no se puede pasar de "%s" a "%s".', $fromLabel, $toLabel)
            );
        }

        $updated = $this->repository->updateStatus($applicationId, $companyId, $status);
        if (!$updated) {
            throw new \RuntimeException('No tienes permisos para actualizar esta postulación o la postulación no existe.');
        }

        return true;
    }

    public function byCompany(int $companyId): array
    {
        return $this->repository->findByCompany($companyId);
    }

    public function byUser(int $userId): array
    {
        return $this->repository->findByUser($userId);
    }

    public function countByStatus(int $userId): array
    {
        return $this->repository->countByStatus($userId);
    }
}
