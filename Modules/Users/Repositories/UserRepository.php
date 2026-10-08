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

    public function findCandidateForDownload(int $candidateId, int $companyId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT users.*,
                    EXISTS(
                        SELECT 1
                        FROM applications a
                        JOIN jobs j ON j.id = a.job_id
                        WHERE a.user_id = users.id
                          AND j.company_id = :company
                    ) AS related_company
             FROM users
             WHERE users.id = :candidate
               AND users.role = "candidate"
             LIMIT 1'
        );

        $statement->execute([
            'candidate' => $candidateId,
            'company' => $companyId,
        ]);

        return $statement->fetch() ?: null;
    }

    public function findProfilePhoto(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT profile_photo_path, profile_photo_mime
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function listForModeration(int $limit = 20, int $offset = 0): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, role, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', max(1, min(50, $limit)), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}
