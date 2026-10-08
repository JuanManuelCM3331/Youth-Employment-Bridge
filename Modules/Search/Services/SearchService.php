<?php

namespace Modules\Search\Services;

use Modules\Jobs\Repositories\JobRepository;

final class SearchService
{
    public function __construct(private JobRepository $jobs)
    {
    }

    public function jobs(string $keyword = '', string $location = '', array $filters = []): array
    {
        return $this->jobs->search(trim($keyword), trim($location), $filters);
    }

    public function jobsPaginated(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        return $this->jobs->searchPaginated($filters, $page, $perPage);
    }
}
