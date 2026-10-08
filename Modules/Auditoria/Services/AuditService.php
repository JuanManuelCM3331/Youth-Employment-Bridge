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
}