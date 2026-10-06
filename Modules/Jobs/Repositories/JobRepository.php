<?php

namespace Modules\Jobs\Repositories;

use App\Core\Database;

final class JobRepository
{
    public function search(string $keyword = '', string $location = ''): array
    {
        $sql = 'SELECT jobs.*, companies.name AS company_name, companies.logo_color
                FROM jobs INNER JOIN companies ON companies.id = jobs.company_id
                WHERE jobs.status = "published"';
        $params = [];

        if ($keyword !== '') {
            $sql .= ' AND (jobs.title LIKE :keyword OR jobs.description LIKE :keyword OR companies.name LIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        }
        if ($location !== '') {
            $sql .= ' AND (jobs.city LIKE :location OR jobs.work_mode LIKE :location)';
            $params['location'] = '%' . $location . '%';
        }

        $sql .= ' ORDER BY jobs.created_at DESC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT jobs.*, companies.name AS company_name FROM jobs INNER JOIN companies ON companies.id = jobs.company_id WHERE jobs.id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }
}