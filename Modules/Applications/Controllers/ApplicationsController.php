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
}
