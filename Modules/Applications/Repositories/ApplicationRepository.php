<?php

namespace Modules\Applications\Repositories;

use App\Core\Database;

final class ApplicationRepository
{
    public function apply(int $jobId, int $userId): void
    {
        Database::connection()->prepare('INSERT INTO applications (job_id,user_id) VALUES (:job,:user)')->execute(['job' => $jobId, 'user' => $userId]);
    }

    public function updateStatus(int $applicationId, int $companyId, string $status): void
    {
        Database::connection()->prepare('UPDATE applications a JOIN jobs j ON j.id=a.job_id SET a.status=:status WHERE a.id=:application AND j.company_id=:company')->execute(['status' => $status, 'application' => $applicationId, 'company' => $companyId]);
    }
}
