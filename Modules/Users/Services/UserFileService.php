<?php

declare(strict_types=1);

namespace Modules\Users\Services;

final class UserFileService
{
    public function storeCv(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Selecciona una hoja de vida válida.');
        }
        if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('La hoja de vida debe pesar máximo 5 MB.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('La carga del archivo no es válida.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];
        if (!isset($allowed[$mime])) {
            throw new \InvalidArgumentException('Solo se permiten archivos PDF, DOC o DOCX.');
        }

        return $this->moveUploadedFile($file, __DIR__ . '/../../../storage/cvs', $allowed[$mime], [
            'path' => true,
            'original_name' => substr(basename((string) $file['name']), 0, 255),
            'mime' => $mime,
            'size' => (int) $file['size'],
        ]);
    }

    public function storeProfilePhoto(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Selecciona una imagen de perfil válida.');
        }
        if (($file['size'] ?? 0) < 1 || $file['size'] > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('La imagen debe pesar máximo 2 MB.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('La carga de la imagen no es válida.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new \InvalidArgumentException('Solo se permiten imágenes JPG, PNG o WEBP.');
        }

        return $this->moveUploadedFile($file, __DIR__ . '/../../../storage/avatars', $allowed[$mime], [
            'path' => true,
            'mime' => $mime,
        ]);
    }

    private function moveUploadedFile(array $file, string $directory, string $extension, array $metadata): array
    {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar el almacenamiento privado.');
        }

        $path = $directory . '/' . bin2hex(random_bytes(24)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        $metadata['path'] = $path;
        return $metadata;
    }
}
