<?php

namespace Modules\Applications\Controllers;

use Modules\Applications\Services\ApplicationService;

final class ApplicationsController
{
    public function __construct(private ApplicationService $applications)
    {
    }

    public function apply(int $jobId, int $userId): void
    {
        $this->applications->apply($jobId, $userId);
    }

    public function updateStatus(int $applicationId, int $companyId, string $status): void
    {
        $this->applications->updateStatus($applicationId, $companyId, $status);
    }

    public function byCompany(int $companyId): array
    {
        return $this->applications->byCompany($companyId);
    }

    public function byUser(int $userId): array
    {
        return $this->applications->byUser($userId);
    }

    public function countByStatus(int $userId): array
    {
        return $this->applications->countByStatus($userId);
    }
}
