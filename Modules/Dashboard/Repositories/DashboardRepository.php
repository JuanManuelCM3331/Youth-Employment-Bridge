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
            'SELECT users.id, users.name, users.email, users.role, users.company_id, users.created_at, companies.name AS company_name
             FROM users
             LEFT JOIN companies ON companies.id = users.company_id
             ORDER BY users.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
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
