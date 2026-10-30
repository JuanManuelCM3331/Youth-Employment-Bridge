<?php

namespace Modules\Search\Services;

use Modules\Jobs\Repositories\JobRepository;

final class SearchService
{
    public function __construct(private JobRepository $jobs)
    {
    }

    /**
     * Busca vacantes aplicando los filtros avanzados.
     *
     * @param array{
     *   title?: string,
     *   city?: string,
     *   work_mode?: string,
     *   experience?: string,
     *   salary_min?: string,
     *   salary_max?: string,
     *   company?: string,
     * } $filters
     * @param array|null $userProfile  Datos del perfil del candidato (opcional)
     */
    public function jobs(array $filters = [], ?array $userProfile = null): array
    {
        return $this->jobs->search($filters);
    }
}
