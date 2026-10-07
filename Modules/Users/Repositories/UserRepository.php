<?php

namespace Modules\Users\Repositories;

use App\Core\Database;

final class UserRepository
{
    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function updateProfile(int $id, array $data): void
    {
        $sql = 'UPDATE users SET name=:name,phone=:phone,location=:location,professional_title=:professional_title,bio=:bio,skills=:skills,experience=:experience,education=:education,linkedin_url=:linkedin_url,portfolio_url=:portfolio_url,availability=:availability';
        if (isset($data['photo_path'])) $sql .= ',profile_photo_path=:photo_path,profile_photo_mime=:photo_mime';
        $sql .= ' WHERE id=:id';
        $data['id'] = $id;
        unset($data['user']);
        Database::connection()->prepare($sql)->execute($data);
    }

    public function updateCv(int $id, array $data): ?string
    {
        $statement = Database::connection()->prepare('SELECT cv_path FROM users WHERE id=:id');
        $statement->execute(['id' => $id]);
        $oldPath = $statement->fetchColumn() ?: null;
        Database::connection()->prepare('UPDATE users SET cv_path=:path,cv_original_name=:original_name,cv_mime=:mime,cv_size=:size,cv_uploaded_at=NOW() WHERE id=:id')->execute([
            'path' => $data['path'], 'original_name' => $data['original_name'], 'mime' => $data['mime'], 'size' => $data['size'], 'id' => $id,
        ]);
        return $oldPath;
    }
}
