<?php

namespace Modules\Jobs\Services;

use Modules\Jobs\Repositories\JobRepository;

final class JobService
{
    public function __construct(private JobRepository $repository)
    {
    }

    public function search(string $keyword = '', string $location = ''): array
    {
        return $this->repository->search(trim($keyword), trim($location));
    }

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }
}