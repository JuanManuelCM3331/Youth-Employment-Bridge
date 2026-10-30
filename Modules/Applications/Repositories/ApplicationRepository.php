<?php

namespace Modules\Applications\Repositories;

use App\Core\Database;

final class ApplicationRepository
{
    public function apply(int $jobId, int $userId): void
    {
        Database::connection()->prepare('INSERT INTO applications (job_id,user_id,status) VALUES (:job,:user,"pending")')->execute(['job' => $jobId, 'user' => $userId]);
    }

    /**
     * Pone en estado 'review' las postulaciones pendientes de un candidato al ser consultado su perfil por una empresa.
     */
    public function markInReview(int $candidateId, int $companyId, ?int $applicationId = null): array
    {
        $db = Database::connection();
        $sql = 'SELECT a.id, j.title AS job_title
                FROM applications a
                JOIN jobs j ON j.id = a.job_id
                WHERE a.user_id = :candidate
                  AND j.company_id = :company
                  AND a.status = "pending"';
        $params = ['candidate' => $candidateId, 'company' => $companyId];
        if ($applicationId !== null && $applicationId > 0) {
            $sql .= ' AND a.id = :app_id';
            $params['app_id'] = $applicationId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $apps = $stmt->fetchAll();

        if (!empty($apps)) {
            $updateSql = 'UPDATE applications a
                          JOIN jobs j ON j.id = a.job_id
                          SET a.status = "review"
                          WHERE a.user_id = :candidate
                            AND j.company_id = :company
                            AND a.status = "pending"';
            $updateParams = ['candidate' => $candidateId, 'company' => $companyId];
            if ($applicationId !== null && $applicationId > 0) {
                $updateSql .= ' AND a.id = :app_id';
                $updateParams['app_id'] = $applicationId;
            }
            $db->prepare($updateSql)->execute($updateParams);
        }

        return $apps;
    }

    /**
     * Estado actual de la vacante, o null si no existe.
     */
    public function jobStatus(int $jobId): ?string
    {
        $statement = Database::connection()->prepare('SELECT status FROM jobs WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $jobId]);
        $status = $statement->fetchColumn();

        return $status === false ? null : (string) $status;
    }

    public function updateStatus(int $applicationId, int $companyId, string $status): void
    {
        Database::connection()->prepare('UPDATE applications a JOIN jobs j ON j.id=a.job_id SET a.status=:status WHERE a.id=:application AND j.company_id=:company')->execute(['status' => $status, 'application' => $applicationId, 'company' => $companyId]);
    }

    public function findByCompany(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT applications.id, applications.status, applications.created_at,
                    jobs.title, users.id AS candidate_id, users.name AS candidate_name,
                    users.email, users.cv_original_name
             FROM applications
             JOIN jobs ON jobs.id = applications.job_id
             JOIN users ON users.id = applications.user_id
             WHERE jobs.company_id = :company
             ORDER BY applications.created_at DESC'
        );
        $statement->execute(['company' => $companyId]);
        return $statement->fetchAll();
    }

    public function findByUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT applications.*, jobs.title, jobs.city, jobs.company_id AS company_id,
                    companies.name AS company_name
             FROM applications
             JOIN jobs ON jobs.id = applications.job_id
             JOIN companies ON companies.id = jobs.company_id
             WHERE applications.user_id = :user
             ORDER BY applications.created_at DESC'
        );
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function countByStatus(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT status, COUNT(*) AS total
             FROM applications
             WHERE user_id = :user
             GROUP BY status'
        );
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function exists(int $jobId, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM applications WHERE job_id = :job AND user_id = :user'
        );
        $statement->execute([
            'job' => $jobId,
            'user' => $userId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
