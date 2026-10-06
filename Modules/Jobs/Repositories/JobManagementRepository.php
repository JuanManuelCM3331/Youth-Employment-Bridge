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
}
