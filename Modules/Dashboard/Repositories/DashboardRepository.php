<?php

namespace Modules\Dashboard\Repositories;

use App\Core\Database;
use PDO;

final class DashboardRepository
{
    public function counts(): array
    {
        $counts = [];
        foreach (['users', 'companies', 'jobs', 'applications'] as $table) {
            $counts[$table] = (int) Database::connection()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $counts['minors'] = (int) Database::connection()->query('SELECT COUNT(*) FROM users WHERE is_minor = 1')->fetchColumn();
        return $counts;
    }

    public function auditLogs(array $filters = [], int $limit = 200): array
    {
        $sql = 'SELECT audit_logs.*, users.name AS user_name 
                FROM audit_logs 
                LEFT JOIN users ON users.id = audit_logs.user_id 
                WHERE 1=1';
        $params = [];

        if (!empty($filters['module'])) {
            $sql .= ' AND audit_logs.module = :module';
            $params['module'] = $filters['module'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND audit_logs.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['action'])) {
            $sql .= ' AND audit_logs.action = :action';
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND DATE(audit_logs.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND DATE(audit_logs.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (audit_logs.description LIKE :search 
                      OR audit_logs.action LIKE :search 
                      OR audit_logs.ip_address LIKE :search 
                      OR users.name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY audit_logs.created_at DESC LIMIT :limit';

        $statement = Database::connection()->prepare($sql);
        foreach ($params as $key => $val) {
            $statement->bindValue($key, $val);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function distinctModules(): array
    {
        return Database::connection()->query('SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL AND module != "" ORDER BY module ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function distinctActions(): array
    {
        return Database::connection()->query('SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL AND action != "" ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function usersList(int $limit = 200): array
    {
        $statement = Database::connection()->prepare(
            'SELECT users.id, users.name, users.email, users.role, users.is_minor, users.minor_blocked, users.company_id, users.created_at, users.status, companies.name AS company_name
             FROM users
             LEFT JOIN companies ON companies.id = users.company_id
             ORDER BY users.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function minorsList(int $limit = 200): array
    {
        $statement = Database::connection()->prepare(
            'SELECT users.id, users.name, users.email, users.cedula, users.role, users.is_minor,
                    users.minor_blocked, users.birth_date, users.status, users.created_at,
                    umd.id AS doc_id,
                    umd.civil_registry_name, umd.civil_registry_path,
                    umd.parent_id_name, umd.parent_id_path,
                    umd.authorization_letter_name, umd.authorization_letter_path,
                    umd.uploaded_at AS docs_uploaded_at,
                    umd.approved_at, umd.approved_by,
                    admin_approver.name AS approved_by_name
             FROM users
             LEFT JOIN user_minor_documents umd ON umd.user_id = users.id
             LEFT JOIN users admin_approver ON admin_approver.id = umd.approved_by
             WHERE users.is_minor = 1
             ORDER BY users.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $now = new \DateTimeImmutable('now');
        foreach ($rows as &$row) {
            $age = null;
            if (!empty($row['birth_date'])) {
                try {
                    $bdate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row['birth_date']);
                    if ($bdate !== false) {
                        $age = (int) $bdate->diff($now)->y;
                    }
                } catch (\Throwable) {}
            }
            $row['age'] = $age ?? 17;
            $row['has_documents'] = !empty($row['civil_registry_path']) && !empty($row['parent_id_path']) && !empty($row['authorization_letter_path']);
        }
        unset($row);

        return $rows;
    }

    public function setMinorBlocked(int $userId, int $blocked): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET minor_blocked = :blocked WHERE id = :id'
        );
        return $statement->execute([
            'blocked' => $blocked,
            'id' => $userId,
        ]);
    }

    public function companiesList(int $limit = 200): array
    {
        $statement = Database::connection()->prepare(
            'SELECT companies.*,
                    (SELECT COUNT(*) FROM jobs WHERE jobs.company_id = companies.id) AS jobs_count,
                    (SELECT COUNT(*) FROM users WHERE users.company_id = companies.id) AS recruiters_count
             FROM companies
             ORDER BY companies.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function jobsList(int $limit = 200): array
    {
        $statement = Database::connection()->prepare(
            'SELECT jobs.id, jobs.title, jobs.city, jobs.work_mode, jobs.status, jobs.created_at,
                    companies.name AS company_name,
                    (SELECT COUNT(*) FROM applications WHERE applications.job_id = jobs.id) AS applications_count
             FROM jobs
             LEFT JOIN companies ON companies.id = jobs.company_id
             ORDER BY jobs.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function applicationsList(int $limit = 200): array
    {
        $statement = Database::connection()->prepare(
            'SELECT applications.id, applications.status, applications.created_at,
                    jobs.title AS job_title,
                    companies.name AS company_name,
                    users.name AS candidate_name,
                    users.email AS candidate_email
             FROM applications
             JOIN jobs ON jobs.id = applications.job_id
             LEFT JOIN companies ON companies.id = jobs.company_id
             LEFT JOIN users ON users.id = applications.user_id
             ORDER BY applications.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function findUser(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function deleteUser(int $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM users WHERE id = :id');
        return $statement->execute(['id' => $id]);
    }

    public function setUserStatus(int $id, string $status): bool
    {
        $statement = Database::connection()->prepare('UPDATE users SET status = :status WHERE id = :id');
        return $statement->execute(['id' => $id, 'status' => $status]);
    }

    public function findCompany(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM companies WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function deleteCompany(int $id): bool
    {
        // Unlink any users tied to this company
        Database::connection()->prepare('UPDATE users SET company_id = NULL WHERE company_id = :id')->execute(['id' => $id]);
        // Delete company (will cascade delete jobs and applications by foreign key constraint)
        $statement = Database::connection()->prepare('DELETE FROM companies WHERE id = :id');
        return $statement->execute(['id' => $id]);
    }

    public function findJob(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT jobs.*, companies.name AS company_name
             FROM jobs
             LEFT JOIN companies ON companies.id = jobs.company_id
             WHERE jobs.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function deleteJob(int $id): bool
    {
        // Las postulaciones, mensajes, favoritos y ocultos se borran en cascada por clave foránea.
        $statement = Database::connection()->prepare('DELETE FROM jobs WHERE id = :id');
        return $statement->execute(['id' => $id]);
    }

    public function findApplication(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT applications.*, jobs.title AS job_title, users.name AS candidate_name, users.email AS candidate_email
             FROM applications
             JOIN jobs ON jobs.id = applications.job_id
             LEFT JOIN users ON users.id = applications.user_id
             WHERE applications.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function deleteApplication(int $id): bool
    {
        // Los mensajes asociados a la postulación se borran en cascada por clave foránea.
        $statement = Database::connection()->prepare('DELETE FROM applications WHERE id = :id');
        return $statement->execute(['id' => $id]);
    }

    public function emailExists(string $email): bool
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $statement->execute(['email' => strtolower(trim($email))]);
        return ((int) $statement->fetchColumn()) > 0;
    }

    public function createAdmin(string $name, string $email, string $password): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, "admin")'
        );
        $statement->execute([
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'password' => $password,
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
