<?php

namespace Modules\Dashboard\Controllers;

use Modules\Dashboard\Services\DashboardService;

final class DashboardController
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    public function summary(): array
    {
        return $this->dashboard->summary();
    }
}
