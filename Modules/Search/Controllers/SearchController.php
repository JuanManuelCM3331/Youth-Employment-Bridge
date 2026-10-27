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

    /**
     * Busca vacantes con los filtros avanzados.
     *
     * @param array $filters  Filtros: title, city, work_mode, experience, salary_min, salary_max, company
     * @param array|null $userProfile  Datos del perfil del candidato (para auto-rellenar filtros)
     */
    public function jobs(array $filters = [], ?array $userProfile = null): array
    {
        return $this->search->jobs($filters, $userProfile);
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
