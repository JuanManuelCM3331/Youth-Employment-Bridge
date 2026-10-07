<?php

namespace Modules\Search\Services;

use Modules\Jobs\Repositories\JobRepository;

final class SearchService
{
    public function __construct(private JobRepository $jobs)
    {
    }

    public function jobs(string $keyword = '', string $location = ''): array
    {
        return $this->jobs->search(trim($keyword), trim($location));
    }
}
