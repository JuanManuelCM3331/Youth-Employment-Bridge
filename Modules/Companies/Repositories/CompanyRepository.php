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

    public function listForModeration(int $limit = 20, int $offset = 0, ?int $verified = null): array
    {
        $sql = 'SELECT id, name, city, industry, verified, contact_email, created_at
                FROM companies';
        $params = [];
        if ($verified !== null) {
            $sql .= ' WHERE verified = :verified';
            $params['verified'] = $verified;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $statement = Database::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, \PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', max(1, min(50, $limit)), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function setVerified(int $companyId, bool $verified): void
    {
        Database::connection()->prepare(
            'UPDATE companies SET verified = :verified WHERE id = :id'
        )->execute([
            'verified' => $verified ? 1 : 0,
            'id' => $companyId,
        ]);
    }
}
