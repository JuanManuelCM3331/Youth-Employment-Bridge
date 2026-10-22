<?php

namespace Modules\Identity\Controllers;

use Modules\Identity\Repositories\IdScanRepository;
use Modules\Identity\Services\IdScanStorage;
use RuntimeException;

final class IdScanController
{
    public function __construct(
        private IdScanStorage $storage,
        private IdScanRepository $scans
    ) {
    }

    /**
     * Entrega la imagen del documento a su titular o a la administración.
     *
     * Las empresas no tienen acceso al reverso de los candidatos: la imagen es
     * un documento sensible y solo se entrega a quien corresponde.
     */
    public function download(?array $user, int $ownerId): void
    {
        if (!$user) {
            throw new RuntimeException('Debes iniciar sesión para consultar tu documento.');
        }

        if ((int) $user['id'] !== $ownerId && $user['role'] !== 'admin') {
            throw new RuntimeException('No tienes permisos para consultar ese documento.');
        }

        $record = $this->scans->findByUser($ownerId);

        if (!$record || empty($record['id_scan_path'])) {
            throw new RuntimeException('Esa cuenta no tiene un documento de identidad asociado.');
        }

        $file = $this->storage->read((string) $record['id_scan_path']);

        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . filesize($file['path']));
        header('Content-Disposition: inline; filename="reverso-documento"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($file['path']);
    }
}
