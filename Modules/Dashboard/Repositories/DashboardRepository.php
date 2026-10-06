<?php

namespace Modules\Dashboard\Repositories;

use App\Core\Database;

final class DashboardRepository
{
    public function counts(): array
    {
        $counts = [];
        foreach (['users', 'companies', 'jobs', 'applications'] as $table) $counts[$table] = (int) Database::connection()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        return $counts;
    }

    public function auditLogs(int $limit = 100): array
    {
        $statement = Database::connection()->prepare('SELECT audit_logs.*,users.name user_name FROM audit_logs LEFT JOIN users ON users.id=audit_logs.user_id ORDER BY audit_logs.created_at DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}
