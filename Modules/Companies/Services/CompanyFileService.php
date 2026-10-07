<?php

declare(strict_types=1);

namespace Modules\Companies\Services;

final class CompanyFileService
{
    public function storeProfilePhoto(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Selecciona una imagen corporativa válida.');
        }

        if (($file['size'] ?? 0) < 1 || $file['size'] > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('La imagen debe pesar máximo 2 MB.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('La carga de la imagen no es válida.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            throw new \InvalidArgumentException('Solo se permiten imágenes JPG, PNG o WEBP.');
        }

        $directory = __DIR__ . '/../../../storage/company-avatars';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar el almacenamiento privado.');
        }

        $path = $directory . '/' . bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        return [
            'path' => $path,
            'mime' => $mime,
        ];
    }
}