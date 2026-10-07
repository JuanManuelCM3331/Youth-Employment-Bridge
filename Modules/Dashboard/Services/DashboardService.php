<?php

namespace Modules\Dashboard\Services;

use Modules\Dashboard\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(private DashboardRepository $repository)
    {
    }

    public function summary(): array
    {
        return ['counts' => $this->repository->counts(), 'logs' => $this->repository->auditLogs()];
    }
}
