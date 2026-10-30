<?php

namespace Modules\Jobs\Repositories;

use App\Core\Database;

final class JobRepository
{
    /**
     * Búsqueda de vacantes por coincidencia parcial con múltiples filtros.
     * Devuelve los resultados ordenados por relevancia (mayor número de coincidencias primero).
     *
     * Filtros admitidos en $filters:
     *   title      - Título del cargo (coincidencia parcial en title y description)
     *   city       - Ciudad (coincidencia parcial). Remoto actúa como ubicación universal.
     *   work_mode  - Modalidad: Remote, Hybrid, Presencial (coincidencia parcial)
     *   experience - Nivel de experiencia (coincidencia parcial)
     *   salary_min - Salario mínimo esperado (filtro duro)
     *   salary_max - Salario máximo esperado (filtro duro)
     *   company    - Nombre de la empresa (coincidencia parcial)
     */
    public function search(array $filters = []): array
    {
        $title      = trim((string) ($filters['title']      ?? ''));
        $city       = trim((string) ($filters['city']       ?? ''));
        $workMode   = trim((string) ($filters['work_mode']  ?? ''));
        $experience = trim((string) ($filters['experience'] ?? ''));
        $salaryMin  = trim((string) ($filters['salary_min'] ?? ''));
        $salaryMax  = trim((string) ($filters['salary_max'] ?? ''));
        $company    = trim((string) ($filters['company']    ?? ''));

        // --- Puntuación de relevancia ---
        // Cada filtro de texto que coincide suma puntos.
        // Los parámetros nombrados aparecen UNA SOLA VEZ (en el SELECT).
        // El filtro HAVING los referencia por el alias, evitando HY093.
        $scoreParts = [];
        $params     = [];

        if ($title !== '') {
            $scoreParts[] = '(CASE WHEN jobs.title LIKE :score_title THEN 3 ELSE 0 END)';
            $scoreParts[] = '(CASE WHEN jobs.description LIKE :score_desc THEN 1 ELSE 0 END)';
            $params['score_title'] = '%' . $title . '%';
            $params['score_desc']  = '%' . $title . '%';
        }
        if ($city !== '') {
            // Remoto es ubicación universal: si se busca por ciudad, los empleos remotos
            // (work_mode = 'Remote' o ciudad 'Remoto') también se consideran válidos para cualquier ciudad.
            if ($workMode !== '' && strtolower($workMode) !== 'remote') {
                $scoreParts[] = '(CASE WHEN jobs.city LIKE :score_city THEN 2 ELSE 0 END)';
            } else {
                $scoreParts[] = '(CASE 
                    WHEN jobs.city LIKE :score_city THEN 3 
                    WHEN jobs.work_mode = "Remote" OR LOWER(jobs.city) LIKE "%remot%" THEN 2 
                    ELSE 0 
                END)';
            }
            $params['score_city'] = '%' . $city . '%';
        }
        if ($workMode !== '') {
            if (strtolower($workMode) === 'remote') {
                $scoreParts[] = '(CASE WHEN jobs.work_mode = "Remote" OR LOWER(jobs.city) LIKE "%remot%" THEN 2 ELSE 0 END)';
            } else {
                $scoreParts[] = '(CASE WHEN jobs.work_mode LIKE :score_wm THEN 2 ELSE 0 END)';
                $params['score_wm'] = '%' . $workMode . '%';
            }
        }
        if ($experience !== '') {
            $scoreParts[] = '(CASE WHEN jobs.experience LIKE :score_exp THEN 2 ELSE 0 END)';
            $params['score_exp'] = '%' . $experience . '%';
        }
        if ($company !== '') {
            $scoreParts[] = '(CASE WHEN companies.name LIKE :score_company THEN 2 ELSE 0 END)';
            $params['score_company'] = '%' . $company . '%';
        }

        $scoreExpr = $scoreParts ? '(' . implode(' + ', $scoreParts) . ')' : '0';

        // --- Filtros duros (salario): descartan registros si no cumplen el rango ---
        $hardWhere = ['jobs.status = "published"'];

        if ($salaryMin !== '' && is_numeric($salaryMin)) {
            $hardWhere[]          = '(jobs.salary_max IS NULL OR jobs.salary_max >= :salary_min)';
            $params['salary_min'] = (float) $salaryMin;
        }
        if ($salaryMax !== '' && is_numeric($salaryMax)) {
            $hardWhere[]          = '(jobs.salary_min IS NULL OR jobs.salary_min <= :salary_max)';
            $params['salary_max'] = (float) $salaryMax;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $hardWhere);

        // Si hay filtros de coincidencia activos, usamos HAVING sobre el alias calculado
        // en el SELECT para que los named params no se dupliquen (evita HY093).
        $havingClause = '';
        $hasMatchFilters = ($title !== '' || $city !== '' || $workMode !== '' || $experience !== '' || $company !== '');
        if ($hasMatchFilters) {
            $havingClause = 'HAVING relevance_score > 0';
        }

        $sql = 'SELECT jobs.*, companies.name AS company_name, companies.logo_color,
                    ' . $scoreExpr . ' AS relevance_score
                FROM jobs
                INNER JOIN companies ON companies.id = jobs.company_id
                ' . $whereClause . '
                ' . $havingClause . '
                ORDER BY relevance_score DESC, jobs.created_at DESC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT jobs.*, companies.name AS company_name
             FROM jobs
             INNER JOIN companies ON companies.id = jobs.company_id
             WHERE jobs.id = :id'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }
}
