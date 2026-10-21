<?php

namespace Modules\Dashboard\Controllers;

use Modules\Dashboard\Services\DashboardService;

final class DashboardController
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    public function summary(array $filters = []): array
    {
        return $this->dashboard->summary($filters);
    }

    public function deleteUser(int $adminId, int $targetUserId): void
    {
        $this->dashboard->deleteUser($adminId, $targetUserId);
    }

    public function deleteCompany(int $adminId, int $companyId): void
    {
        $this->dashboard->deleteCompany($adminId, $companyId);
    }

    public function deleteJob(int $adminId, int $jobId): void
    {
        $this->dashboard->deleteJob($adminId, $jobId);
    }

    public function deleteApplication(int $adminId, int $applicationId): void
    {
        $this->dashboard->deleteApplication($adminId, $applicationId);
    }

    public function createAdmin(int $adminId, array $data): int
    {
        return $this->dashboard->createAdmin($adminId, $data);
    }
}
