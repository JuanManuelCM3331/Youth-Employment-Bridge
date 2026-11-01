<?php

declare(strict_types=1);

/**
 * Copia a MySQL los archivos que todavía estén en disco y que tengan una fila
 * con su ruta en la base de datos. No borra los archivos originales.
 *
 * Uso: php database/backfill_file_storage.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por consola.');
}

$root = dirname(__DIR__);
spl_autoload_register(function (string $class) use ($root): void {
    foreach (['App\\' => $root . '/app/', 'Modules\\' => $root . '/Modules/'] as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});

$pdo = App\Core\Database::connection();
$copied = 0;
$missing = 0;

$readFile = static function (string $path): ?string {
    if ($path === '' || str_starts_with($path, 'db://') || !is_file($path)) {
        return null;
    }
    $data = file_get_contents($path);
    return $data === false ? null : $data;
};

// --- Usuarios: foto de perfil y hoja de vida ---
$users = $pdo->query(
    'SELECT id, profile_photo_path, cv_path FROM users
     WHERE (profile_photo_data IS NULL AND profile_photo_path IS NOT NULL)
        OR (cv_data IS NULL AND cv_path IS NOT NULL)'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    $update = [];
    $params = ['id' => $user['id']];

    $photo = $readFile((string) $user['profile_photo_path']);
    if ($photo !== null) {
        $update[] = 'profile_photo_data=:photo_data, profile_photo_path="db://profile-photo"';
        $params['photo_data'] = $photo;
        $copied++;
    } elseif (!empty($user['profile_photo_path'])) {
        $missing++;
    }

    $cv = $readFile((string) $user['cv_path']);
    if ($cv !== null) {
        $update[] = 'cv_data=:cv_data, cv_path="db://cv"';
        $params['cv_data'] = $cv;
        $copied++;
    } elseif (!empty($user['cv_path'])) {
        $missing++;
    }

    if ($update) {
        $pdo->prepare('UPDATE users SET ' . implode(', ', $update) . ' WHERE id=:id')->execute($params);
    }
}

// --- Empresas: logo ---
$companies = $pdo->query(
    'SELECT id, profile_photo_path FROM companies
     WHERE profile_photo_data IS NULL AND profile_photo_path IS NOT NULL'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($companies as $company) {
    $logo = $readFile((string) $company['profile_photo_path']);
    if ($logo !== null) {
        $pdo->prepare(
            'UPDATE companies SET profile_photo_data=:data, profile_photo_path="db://company-photo" WHERE id=:id'
        )->execute(['data' => $logo, 'id' => $company['id']]);
        $copied++;
    } else {
        $missing++;
    }
}

// --- Menores: 3 documentos ---
$documents = $pdo->query(
    'SELECT * FROM user_minor_documents
     WHERE civil_registry_data IS NULL OR parent_id_data IS NULL OR authorization_letter_data IS NULL'
)->fetchAll(PDO::FETCH_ASSOC);

$documentColumns = [
    'civil_registry' => ['data' => 'civil_registry_data', 'path' => 'civil_registry_path'],
    'parent_id' => ['data' => 'parent_id_data', 'path' => 'parent_id_path'],
    'authorization_letter' => ['data' => 'authorization_letter_data', 'path' => 'authorization_letter_path'],
];

foreach ($documents as $document) {
    $update = [];
    $params = ['id' => $document['id']];

    foreach ($documentColumns as $type => $columns) {
        if (!empty($document[$columns['data']])) {
            continue;
        }
        $binary = $readFile((string) ($document[$columns['path']] ?? ''));
        if ($binary !== null) {
            $update[] = $columns['data'] . '=:' . $type . '_data';
            $update[] = $columns['path'] . '=:' . $type . '_path';
            $params[$type . '_data'] = $binary;
            $params[$type . '_path'] = 'db://minor-documents/' . $type;
            $copied++;
        } elseif (!empty($document[$columns['path']])) {
            $missing++;
        }
    }

    if ($update) {
        $pdo->prepare('UPDATE user_minor_documents SET ' . implode(', ', $update) . ' WHERE id=:id')->execute($params);
    }
}

echo "Archivos copiados a MySQL: {$copied}\n";
echo "Rutas sin archivo disponible (no se pudieron copiar): {$missing}\n";
