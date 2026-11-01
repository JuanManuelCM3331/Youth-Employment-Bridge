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

        $data = file_get_contents($file['tmp_name']);
        if ($data === false) {
            throw new \RuntimeException('No se pudo leer la imagen corporativa.');
        }

        return [
            'data' => $data,
            'path' => 'db://company-photo',
            'mime' => $mime,
        ];
    }
}
