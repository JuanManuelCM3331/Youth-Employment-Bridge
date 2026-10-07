<?php

namespace Modules\Companies\Services;

use Modules\Companies\Repositories\CompanyRepository;

final class CompanyProfileService
{
    public function __construct(private CompanyRepository $repository)
    {
    }

    public function update(int $companyId, array $data): void
    {
        $this->repository->updateProfile($companyId, $data);
    }

    public function find(int $companyId): ?array
    {
        return $this->repository->find($companyId);
    }

    public function currentPhoto(int $companyId): ?string
    {
        return $this->repository->currentPhoto($companyId);
    }
}
