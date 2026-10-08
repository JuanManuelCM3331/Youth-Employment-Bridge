<?php

namespace Modules\Jobs\Controllers;

use Modules\Jobs\Services\JobManagementService;

final class JobsController
{
    public function __construct(private JobManagementService $jobs)
    {
    }

    public function create(int $companyId, array $data): int
    {
        return $this->jobs->create($companyId, $data);
    }

    public function byCompany(int $companyId): array
    {
        return $this->jobs->byCompany($companyId);
    }

    public function update(int $companyId, int $jobId, array $data): void
    {
        $this->jobs->update($companyId, $jobId, $data);
    }

    public function updateStatus(int $companyId, int $jobId, string $status): void
    {
        $this->jobs->updateStatus($companyId, $jobId, $status);
    }

    public function delete(int $companyId, int $jobId): void
    {
        $this->jobs->delete($companyId, $jobId);
    }
}
