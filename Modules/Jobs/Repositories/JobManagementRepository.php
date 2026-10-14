<?php

namespace Modules\Jobs\Repositories;

use App\Core\Database;

final class JobManagementRepository
{
    public function create(int $companyId, array $data): int
    {
        $statement = Database::connection()->prepare('INSERT INTO jobs (company_id,title,description,city,work_mode,experience,salary_min,salary_max,status) VALUES (:company,:title,:description,:city,:mode,:experience,:min,:max,:status)');
        $statement->execute(['company' => $companyId, 'title' => $data['title'], 'description' => $data['description'], 'city' => $data['city'], 'mode' => $data['work_mode'], 'experience' => $data['experience'], 'min' => $data['salary_min'], 'max' => $data['salary_max'], 'status' => $data['status']]);
        return (int) Database::connection()->lastInsertId();
    }

    public function findByCompany(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT jobs.*, COUNT(applications.id) AS applications_count
             FROM jobs
             LEFT JOIN applications ON applications.job_id = jobs.id
             WHERE jobs.company_id = :company
             GROUP BY jobs.id
             ORDER BY jobs.created_at DESC'
        );
        $statement->execute(['company' => $companyId]);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM jobs WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function update(int $id, int $companyId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE jobs SET title = :title, description = :description, city = :city, work_mode = :mode, experience = :experience, salary_min = :min, salary_max = :max WHERE id = :id AND company_id = :company'
        );
        $statement->execute([
            'id' => $id,
            'company' => $companyId,
            'title' => $data['title'],
            'description' => $data['description'],
            'city' => $data['city'],
            'mode' => $data['work_mode'],
            'experience' => $data['experience'],
            'min' => $data['salary_min'],
            'max' => $data['salary_max'],
        ]);
    }

    public function updateStatus(int $id, int $companyId, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE jobs SET status = :status WHERE id = :id AND company_id = :company'
        );
        $statement->execute([
            'id' => $id,
            'company' => $companyId,
            'status' => $status,
        ]);
    }
}
