<?php

namespace Modules\Companies\Controllers;

use Modules\Companies\Services\CompanyProfileService;

final class CompaniesController
{
    public function __construct(private CompanyProfileService $profiles)
    {
    }

    public function update(int $companyId, int $userId, array $data, ?array $photo): void
    {
        $this->profiles->update($companyId, $userId, $data, $photo);
    }

    public function downloadPhoto(?array $user, int $companyId): void
    {
        $photo = $this->profiles->photoForDownload($user, $companyId);

        header('Content-Type: ' . $photo['mime']);
        header('Content-Length: ' . filesize($photo['path']));
        header('Cache-Control: private, max-age=3600');

        readfile($photo['path']);
    }
}
