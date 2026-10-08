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

    public function listForModeration(int $limit = 20, int $offset = 0, ?int $verified = null): array
    {
        return $this->profiles->listForModeration($limit, $offset, $verified);
    }

    public function setVerified(int $companyId, bool $verified): void
    {
        $this->profiles->setVerified($companyId, $verified);
    }
}
