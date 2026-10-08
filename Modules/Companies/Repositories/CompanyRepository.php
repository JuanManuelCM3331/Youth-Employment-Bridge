<?php

namespace Modules\Companies\Repositories;

use App\Core\Database;

final class CompanyRepository
{
    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM companies WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function updateProfile(int $id, array $data): void
    {
        $sql = 'UPDATE companies SET name=:name,description=:description,city=:city,industry=:industry,website=:website,phone=:phone,contact_email=:contact_email,size=:size';
        if (isset($data['photo_path'])) $sql .= ',profile_photo_path=:photo_path,profile_photo_mime=:photo_mime';
        $sql .= ' WHERE id=:id';
        $data['id'] = $id;
        unset($data['company']);
        Database::connection()->prepare($sql)->execute($data);
    }

    public function currentPhoto(int $id): ?string
    {
        $statement = Database::connection()->prepare('SELECT profile_photo_path FROM companies WHERE id=:id');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() ?: null;
    }
}
