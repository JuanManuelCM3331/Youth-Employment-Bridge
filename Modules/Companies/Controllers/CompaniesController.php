<?php

namespace Modules\Companies\Controllers;

use Modules\Companies\Services\CompanyProfileService;

final class CompaniesController
{
    public function __construct(private CompanyProfileService $profiles)
    {
    }

    public function update(int $companyId, array $data): void
    {
        $this->profiles->update($companyId, $data);
    }
}
