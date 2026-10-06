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
}
