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

    public function find(int $id): ?array
    {
        return $this->jobs->find($id);
    }

    public function update(int $id, int $companyId, array $data): void
    {
        $this->jobs->update($id, $companyId, $data);
    }

    public function close(int $id, int $companyId): void
    {
        $this->jobs->close($id, $companyId);
    }
}
