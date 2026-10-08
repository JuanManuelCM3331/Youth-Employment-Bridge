<?php

namespace Modules\Auditoria\Controllers;

use Modules\Auditoria\Services\AuditService;

final class AuditController
{
    public function __construct(private AuditService $audit)
    {
    }

    public function logs(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->audit->logs($filters, $page, $perPage);
    }
}
