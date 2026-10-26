<?php

declare(strict_types=1);

namespace Modules\Identity\Services;

use App\Core\Database;
use InvalidArgumentException;
use Modules\Auditoria\Services\AuditService;
use PDO;
use RuntimeException;

final class MinorDocumentService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    private string $storagePath;
    private PDO $db;
    private AuditService $audit;

    public function __construct(?string $storagePath = null, ?PDO $db = null, ?AuditService $audit = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/../../../storage/minor-documents';
        $this->db = $db ?? Database::connection();
        $this->audit = $audit ?? new AuditService();

        $this->ensureStorageDirectory();
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0750, true);
        }

        $htaccess = rtrim($this->storagePath, '/\\') . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\n");
        }
    }

    /**
     * Obtiene el registro de documentos de un menor de edad.
     */
    public function getDocumentsByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM user_minor_documents WHERE user_id = :user_id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Comprueba si un usuario es menor de edad y está bloqueado.
     */
    public function isUserBlockedMinor(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT is_minor, minor_blocked FROM users WHERE id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        return (int) $row['is_minor'] === 1 && (int) $row['minor_blocked'] === 1;
    }

    /**
     * Valida y almacena los 3 documentos obligatorios para menores de edad.
     *
     * @param int $userId ID del usuario menor
     * @param array $files Arreglo con los 3 archivos ($_FILES)
     * @return array Metadatos de los documentos guardados
     */
    public function storeDocuments(int $userId, array $files): array
    {
        // Acepta nombres con o sin prefijo minor_
        $fileRegistry = $files['minor_civil_registry'] ?? $files['civil_registry'] ?? null;
        $fileParent = $files['minor_parent_id'] ?? $files['parent_id'] ?? null;
        $fileAuth = $files['minor_authorization'] ?? $files['authorization_letter'] ?? null;

        if (!$fileRegistry || ($fileRegistry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException(
                'Debes adjuntar el archivo: Copia legible del Registro Civil de Nacimiento del menor de edad.'
            );
        }

        if (!$fileParent || ($fileParent['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException(
                'Debes adjuntar el archivo: Documento de identidad (Cédula de ciudadanía) de los padres o representantes legales.'
            );
        }

        if (!$fileAuth || ($fileAuth['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException(
                'Debes adjuntar el archivo: Carta de autorización o formato específico firmado y autenticado según el trámite requerido.'
            );
        }

        $regData = $this->processUploadedFile($fileRegistry, 'reg_civil_' . $userId);
        $parentData = $this->processUploadedFile($fileParent, 'cedula_padre_' . $userId);
        $authData = $this->processUploadedFile($fileAuth, 'carta_auth_' . $userId);

        $existing = $this->getDocumentsByUserId($userId);

        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE user_minor_documents SET
                    civil_registry_path = :reg_path,
                    civil_registry_name = :reg_name,
                    civil_registry_mime = :reg_mime,
                    parent_id_path = :parent_path,
                    parent_id_name = :parent_name,
                    parent_id_mime = :parent_mime,
                    authorization_letter_path = :auth_path,
                    authorization_letter_name = :auth_name,
                    authorization_letter_mime = :auth_mime,
                    uploaded_at = NOW(),
                    approved_at = NULL,
                    approved_by = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                'reg_path' => $regData['path'],
                'reg_name' => $regData['name'],
                'reg_mime' => $regData['mime'],
                'parent_path' => $parentData['path'],
                'parent_name' => $parentData['name'],
                'parent_mime' => $parentData['mime'],
                'auth_path' => $authData['path'],
                'auth_name' => $authData['name'],
                'auth_mime' => $authData['mime'],
                'id' => $existing['id'],
            ]);
            $docId = (int) $existing['id'];
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO user_minor_documents (
                    user_id,
                    civil_registry_path, civil_registry_name, civil_registry_mime,
                    parent_id_path, parent_id_name, parent_id_mime,
                    authorization_letter_path, authorization_letter_name, authorization_letter_mime,
                    uploaded_at, created_at
                ) VALUES (
                    :user_id,
                    :reg_path, :reg_name, :reg_mime,
                    :parent_path, :parent_name, :parent_mime,
                    :auth_path, :auth_name, :auth_mime,
                    NOW(), NOW()
                )'
            );
            $stmt->execute([
                'user_id' => $userId,
                'reg_path' => $regData['path'],
                'reg_name' => $regData['name'],
                'reg_mime' => $regData['mime'],
                'parent_path' => $parentData['path'],
                'parent_name' => $parentData['name'],
                'parent_mime' => $parentData['mime'],
                'auth_path' => $authData['path'],
                'auth_name' => $authData['name'],
                'auth_mime' => $authData['mime'],
            ]);
            $docId = (int) $this->db->lastInsertId();
        }

        // Asegurar que el usuario queda marcado como menor y con bloqueo activo
        $updUser = $this->db->prepare('UPDATE users SET is_minor = 1, minor_blocked = 1 WHERE id = :id');
        $updUser->execute(['id' => $userId]);

        $this->audit->log(
            'minor_documents_uploaded',
            'Users',
            'El aspirante menor de edad cargó los 3 documentos de autorización legal.',
            'success',
            $userId,
            [
                'document_id' => $docId,
                'civil_registry' => $regData['name'],
                'parent_id' => $parentData['name'],
                'authorization_letter' => $authData['name'],
            ]
        );

        return [
            'id' => $docId,
            'civil_registry' => $regData,
            'parent_id' => $parentData,
            'authorization_letter' => $authData,
        ];
    }

    /**
     * Procesa y valida un archivo individual subido.
     */
    private function processUploadedFile(array $file, string $prefix): array
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Error al subir el archivo ' . htmlspecialchars($file['name'] ?? ''));
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'El archivo ' . htmlspecialchars($file['name'] ?? '') . ' supera el tamaño máximo de 10 MB.'
            );
        }

        $originalName = basename((string) ($file['name'] ?? 'documento'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException(
                'El formato de "' . htmlspecialchars($originalName) . '" no está permitido. Formatos admitidos: PDF, JPG, PNG, DOC, DOCX.'
            );
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $mime = $file['type'] ?? 'application/octet-stream';

        if (function_exists('finfo_open') && is_file($tmpName)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = finfo_file($finfo, $tmpName);
                if ($detectedMime !== false) {
                    $mime = $detectedMime;
                }
                finfo_close($finfo);
            }
        }

        $safeFilename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = rtrim($this->storagePath, '/\\') . '/' . $safeFilename;

        // Permite soporte para pruebas simuladas (donde tmp_name no viene de HTTP upload)
        if (!empty($file['test_mode'])) {
            if (!copy($tmpName, $destination)) {
                throw new RuntimeException('No se pudo guardar el archivo en almacenamiento.');
            }
        } else {
            if (!move_uploaded_file($tmpName, $destination) && !copy($tmpName, $destination)) {
                throw new RuntimeException('No se pudo guardar el archivo en almacenamiento.');
            }
        }

        return [
            'path' => $destination,
            'name' => $originalName,
            'mime' => $mime,
            'size' => $size,
        ];
    }

    /**
     * Prepara la descarga de un documento de menor de edad.
     */
    public function getDocumentForDownload(int $userId, string $type): array
    {
        $docs = $this->getDocumentsByUserId($userId);
        if (!$docs) {
            throw new RuntimeException('No se encontraron documentos registrados para este usuario.');
        }

        $columnMap = [
            'civil_registry' => ['path' => 'civil_registry_path', 'name' => 'civil_registry_name', 'mime' => 'civil_registry_mime'],
            'minor_civil_registry' => ['path' => 'civil_registry_path', 'name' => 'civil_registry_name', 'mime' => 'civil_registry_mime'],
            'parent_id' => ['path' => 'parent_id_path', 'name' => 'parent_id_name', 'mime' => 'parent_id_mime'],
            'minor_parent_id' => ['path' => 'parent_id_path', 'name' => 'parent_id_name', 'mime' => 'parent_id_mime'],
            'authorization_letter' => ['path' => 'authorization_letter_path', 'name' => 'authorization_letter_name', 'mime' => 'authorization_letter_mime'],
            'minor_authorization' => ['path' => 'authorization_letter_path', 'name' => 'authorization_letter_name', 'mime' => 'authorization_letter_mime'],
        ];

        if (!isset($columnMap[$type])) {
            throw new InvalidArgumentException('Tipo de documento no válido.');
        }

        $mapping = $columnMap[$type];
        $path = (string) ($docs[$mapping['path']] ?? '');
        $name = (string) ($docs[$mapping['name']] ?? 'documento');
        $mime = (string) ($docs[$mapping['mime']] ?? 'application/octet-stream');

        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('El archivo físico del documento no se encuentra disponible.');
        }

        return [
            'path' => $path,
            'name' => $name,
            'mime' => $mime,
        ];
    }

    /**
     * El administrador retira el bloqueo al menor de edad.
     */
    public function unblockMinor(int $adminId, int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            throw new RuntimeException('El usuario no existe.');
        }

        $updUser = $this->db->prepare('UPDATE users SET minor_blocked = 0 WHERE id = :id');
        $updUser->execute(['id' => $userId]);

        $updDocs = $this->db->prepare(
            'UPDATE user_minor_documents SET approved_at = NOW(), approved_by = :admin_id WHERE user_id = :id'
        );
        $updDocs->execute(['admin_id' => $adminId, 'id' => $userId]);

        $this->audit->log(
            'minor_unblocked',
            'Users',
            "El administrador retiró el bloqueo al aspirante menor de edad: {$targetUser['name']} ({$targetUser['email']}).",
            'success',
            $adminId,
            ['target_user_id' => $userId, 'email' => $targetUser['email']]
        );
    }

    /**
     * El administrador reactiva el bloqueo al menor de edad.
     */
    public function blockMinor(int $adminId, int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            throw new RuntimeException('El usuario no existe.');
        }

        $updUser = $this->db->prepare('UPDATE users SET minor_blocked = 1 WHERE id = :id');
        $updUser->execute(['id' => $userId]);

        $this->audit->log(
            'minor_blocked',
            'Users',
            "El administrador reactivó el bloqueo al aspirante menor de edad: {$targetUser['name']} ({$targetUser['email']}).",
            'success',
            $adminId,
            ['target_user_id' => $userId, 'email' => $targetUser['email']]
        );
    }
}
