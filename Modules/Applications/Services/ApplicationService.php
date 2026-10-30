<?php

namespace Modules\Applications\Services;

use Modules\Applications\Repositories\ApplicationRepository;

final class ApplicationService
{
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

    public function updateStatus(int $applicationId, int $companyId, string $status): void
    {
        if (!in_array($status, ['pending', 'review', 'interview', 'accepted', 'rejected'], true)) throw new \InvalidArgumentException('Estado de postulación inválido.');
        $this->repository->updateStatus($applicationId, $companyId, $status);
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
