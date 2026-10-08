<?php

namespace Modules\Jobs\Repositories;

use App\Core\Database;

final class JobRepository
{
    public function search(string $keyword = '', string $location = '', array $filters = []): array
    {
        [$sql, $params] = $this->buildSearchQuery($keyword, $location, $filters);
        $sql .= ' ORDER BY jobs.created_at DESC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function searchPaginated(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(30, $perPage));
        $offset = ($page - 1) * $perPage;
        $keyword = trim((string) ($filters['q'] ?? ''));
        $location = trim((string) ($filters['location'] ?? ''));

        [$baseSql, $params] = $this->buildSearchQuery($keyword, $location, $filters);

        $countStatement = Database::connection()->prepare('SELECT COUNT(*) FROM (' . $baseSql . ') AS jobs_filtered');
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $sql = $baseSql . ' ORDER BY jobs.created_at DESC LIMIT :limit OFFSET :offset';
        $statement = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => ($offset + $perPage) < $total,
        ];
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT jobs.*, companies.name AS company_name FROM jobs INNER JOIN companies ON companies.id = jobs.company_id WHERE jobs.id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    private function buildSearchQuery(string $keyword, string $location, array $filters): array
    {
        $sql = 'SELECT jobs.*, companies.name AS company_name, companies.logo_color
                FROM jobs INNER JOIN companies ON companies.id = jobs.company_id
                WHERE jobs.status = "published"';
        $params = [];

        if ($keyword !== '') {
            $sql .= ' AND (jobs.title LIKE :keyword_title OR jobs.description LIKE :keyword_description OR companies.name LIKE :keyword_company)';
            $value = '%' . $keyword . '%';
            $params['keyword_title'] = $value;
            $params['keyword_description'] = $value;
            $params['keyword_company'] = $value;
        }

        if ($location !== '') {
            $sql .= ' AND (jobs.city LIKE :location OR jobs.work_mode LIKE :location)';
            $params['location'] = '%' . $location . '%';
        }

        $workMode = trim((string) ($filters['work_mode'] ?? ''));
        if ($workMode !== '') {
            $sql .= ' AND jobs.work_mode = :work_mode';
            $params['work_mode'] = $workMode;
        }

        $experience = trim((string) ($filters['experience'] ?? ''));
        if ($experience !== '') {
            $sql .= ' AND jobs.experience LIKE :experience';
            $params['experience'] = '%' . $experience . '%';
        }

        $salaryMin = $filters['salary_min'] ?? null;
        if ($salaryMin !== null && $salaryMin !== '') {
            $sql .= ' AND jobs.salary_max >= :salary_min';
            $params['salary_min'] = (float) $salaryMin;
        }

        $salaryMax = $filters['salary_max'] ?? null;
        if ($salaryMax !== null && $salaryMax !== '') {
            $sql .= ' AND jobs.salary_min <= :salary_max';
            $params['salary_max'] = (float) $salaryMax;
        }

        return [$sql, $params];
    }
}