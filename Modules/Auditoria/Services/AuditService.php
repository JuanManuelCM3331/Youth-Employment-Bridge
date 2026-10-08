<?php

namespace Modules\Auditoria\Services;

use App\Core\Database;

final class AuditService
{
    public function log(string $action, string $module, string $description, string $status = 'success', ?int $userId = null, array $payload = []): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, module, route, status, description, ip_address, user_agent, payload)
             VALUES (:user_id, :action, :module, :route, :status, :description, :ip_address, :user_agent, :payload)'
        );
        $statement->execute([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'route' => $_SERVER['REQUEST_URI'] ?? 'cli',
            'status' => $status,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 255),
            'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function logs(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (($module = trim((string) ($filters['module'] ?? ''))) !== '') {
            $conditions[] = 'audit_logs.module = :module';
            $params['module'] = $module;
        }

        if (($status = trim((string) ($filters['status'] ?? ''))) !== '') {
            $conditions[] = 'audit_logs.status = :status';
            $params['status'] = $status;
        }

        $where = $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';
        $base = ' FROM audit_logs
                  LEFT JOIN users ON users.id = audit_logs.user_id' . $where;

        $countStatement = Database::connection()->prepare('SELECT COUNT(*)' . $base);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $statement = Database::connection()->prepare(
            'SELECT audit_logs.*, users.name AS user_name' . $base . '
             ORDER BY audit_logs.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
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
}