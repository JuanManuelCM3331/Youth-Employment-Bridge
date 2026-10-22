<?php

namespace Modules\Identity\Repositories;

use App\Core\Database;

final class IdScanRepository
{
    /**
     * Datos del reverso de identidad guardado para una cuenta.
     *
     * @return array{id_scan_path: string, id_scan_mime: string, id_scan_document: string, id_scan_source: string, id_scan_verified_at: string}|null
     */
    public function findByUser(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id_scan_path, id_scan_mime, id_scan_document, id_scan_source, id_scan_verified_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $userId]);

        $row = $statement->fetch();

        return $row ?: null;
    }
}
