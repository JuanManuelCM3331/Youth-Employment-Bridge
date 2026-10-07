<?php

namespace Modules\Search\Controllers;

use Modules\Search\Services\JobInteractionService;
use Modules\Search\Services\SearchService;

final class SearchController
{
    public function __construct(
        private SearchService $search,
        private JobInteractionService $interactions
    ) {
    }

    public function jobs(string $keyword = '', string $location = ''): array
    {
        return $this->search->jobs($keyword, $location);
    }

    public function toggleSaved(int $userId, int $jobId): void
    {
        $this->interactions->toggleSaved($userId, $jobId);
    }

    public function hide(int $userId, int $jobId): void
    {
        $this->interactions->hide($userId, $jobId);
    }

    public function hiddenJobIds(int $userId): array
    {
        return $this->interactions->hiddenJobIds($userId);
    }

    public function savedJobIds(int $userId): array
    {
        return $this->interactions->savedJobIds($userId);
    }

    public function savedJobs(int $userId): array
    {
        return $this->interactions->savedJobs($userId);
    }
}
