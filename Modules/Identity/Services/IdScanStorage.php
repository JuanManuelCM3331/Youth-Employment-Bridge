<?php

namespace Modules\Identity\Services;

use InvalidArgumentException;
use Modules\Identity\Dto\IdScanConfig;
use Modules\Identity\Dto\ScannedId;
use RuntimeException;

/**
 * Almacenamiento privado de la imagen del reverso del documento de identidad.
 *
 * La imagen nunca queda en una carpeta pública: se guarda en storage/id-scans
 * con un nombre aleatorio y se entrega únicamente a través de una descarga
 * controlada. Junto a cada imagen se escribe el JSON con los datos leídos.
 */
final class IdScanStorage
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private IdScanConfig $config)
    {
    }

    /**
     * Guarda la imagen del documento (archivo subido o imagen codificada en base64).
     *
     * @param array|null $file Archivo recibido en $_FILES.
     * @param string|null $base64 Imagen en base64 (respaldo para la cámara).
     */
    public function store(?array $file, ?string $base64, ScannedId $scan): array
    {
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return $this->storeUploadedFile($file, $scan);
        }

        if (is_string($base64) && trim($base64) !== '') {
            return $this->storeEncodedImage($base64, $scan);
        }

        throw new InvalidArgumentException(
            'Adjunta la foto del reverso de tu cédula o tarjeta de identidad para continuar.'
        );
    }

    /**
     * Guarda un archivo subido por el formulario.
     */
    public function storeUploadedFile(array $file, ScannedId $scan): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('La imagen del documento no se pudo cargar. Intenta de nuevo.');
        }

        if (($file['size'] ?? 0) < 1 || ($file['size'] ?? 0) > $this->config->maxSize) {
            throw new InvalidArgumentException(
                'La imagen del documento supera el tamaño permitido (máximo '
                . round($this->config->maxSize / 1048576) . ' MB).'
            );
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('La carga de la imagen del documento no es válida.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new InvalidArgumentException('Solo se permiten imágenes JPG, PNG o WEBP del documento.');
        }

        $binary = (string) file_get_contents($file['tmp_name']);

        return $this->persist($binary, self::ALLOWED_MIME[$mime], $mime, $scan);
    }

    /**
     * Guarda una imagen que llega codificada en base64 (captura con la cámara).
     */
    public function storeEncodedImage(string $base64, ScannedId $scan): array
    {
        $payload = preg_replace('#^data:image/[a-z0-9.+-]+;base64,#i', '', trim($base64)) ?? '';
        $binary = base64_decode(strtr($payload, ' ', '+'), true);

        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('La imagen capturada con la cámara no es válida.');
        }

        if (strlen($binary) > $this->config->maxSize) {
            throw new InvalidArgumentException(
                'La imagen del documento supera el tamaño permitido (máximo '
                . round($this->config->maxSize / 1048576) . ' MB).'
            );
        }

        $temporary = tempnam(sys_get_temp_dir(), 'yeb_id_');

        if ($temporary === false) {
            throw new RuntimeException('No se pudo preparar el almacenamiento del documento.');
        }

        file_put_contents($temporary, $binary);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
        @unlink($temporary);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new InvalidArgumentException('Solo se permiten imágenes JPG, PNG o WEBP del documento.');
        }

        return $this->persist($binary, self::ALLOWED_MIME[$mime], $mime, $scan);
    }

    /**
     * Devuelve la imagen almacenada para poder entregarla de forma controlada.
     *
     * @throws InvalidArgumentException cuando la ruta no pertenece al almacenamiento privado.
     */
    public function read(string $path): array
    {
        $directory = realpath($this->directory());
        $real = realpath($path);

        if ($directory === false || $real === false) {
            throw new InvalidArgumentException('El documento no está disponible.');
        }

        if (!str_starts_with($real, $directory . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('El documento no está disponible.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($real);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new InvalidArgumentException('El documento no está disponible.');
        }

        return ['path' => $real, 'mime' => $mime];
    }

    /**
     * Elimina una imagen almacenada y su registro JSON.
     *
     * Se usa cuando el registro no llega a completarse (por ejemplo, si el
     * INSERT de la cuenta falla) para no dejar archivos huérfanos.
     */
    public function remove(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        $directory = realpath($this->directory());
        $real = realpath($path);

        if ($directory === false || $real === false) {
            return;
        }

        if (!str_starts_with($real, $directory . DIRECTORY_SEPARATOR)) {
            return;
        }

        @unlink($real);
        @unlink($directory . '/' . pathinfo($real, PATHINFO_FILENAME) . '.json');
    }

    /**
     * Escribe la imagen y su registro JSON en el almacenamiento privado.
     */
    private function persist(string $binary, string $extension, string $mime, ScannedId $scan): array
    {
        $directory = $this->directory();

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento privado del documento.');
        }

        $id = bin2hex(random_bytes(16));
        $path = $directory . '/' . $id . '.' . $extension;

        if (file_put_contents($path, $binary, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar la imagen del documento.');
        }

        $record = array_merge($scan->toArray(), [
            'id' => $id,
            'image' => basename($path),
            'mime' => $mime,
            'size' => strlen($binary),
            'createdAt' => date('c'),
        ]);

        file_put_contents(
            $directory . '/' . $id . '.json',
            json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return [
            'path' => $path,
            'mime' => $mime,
            'size' => strlen($binary),
            'source' => $scan->source,
            'document' => $scan->documentNumber,
            'data' => json_encode($record, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function directory(): string
    {
        return __DIR__ . '/../../../storage/id-scans';
    }
}
