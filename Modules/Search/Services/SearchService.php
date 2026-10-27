<?php

namespace Modules\Search\Services;

use Modules\Jobs\Repositories\JobRepository;

final class SearchService
{
    public function __construct(private JobRepository $jobs)
    {
    }

    /**
     * Busca vacantes aplicando los filtros avanzados y auto-completando desde el perfil
     * del candidato si se le pasa (professional_title y location).
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
     * @param array|null $userProfile  Fila de usuario con professional_title y location
     */
    public function jobs(array $filters = [], ?array $userProfile = null): array
    {
        // Auto-rellenar filtros vacíos con datos del perfil del candidato
        if ($userProfile) {
            if (empty($filters['title']) && !empty($userProfile['professional_title'])) {
                $filters['title'] = (string) $userProfile['professional_title'];
            }
            if (empty($filters['city']) && !empty($userProfile['location'])) {
                $filters['city'] = (string) $userProfile['location'];
            }
        }

        return $this->jobs->search($filters);
    }
}
