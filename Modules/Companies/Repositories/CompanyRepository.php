<?php

namespace Modules\Companies\Repositories;

use App\Core\Database;

final class CompanyRepository
{
    /**
     * Columnas de empresa sin el BLOB del logo; se expone su tamaño para las vistas.
     */
    private const PROFILE_COLUMNS = 'id, name, description, city, industry, website, phone,
        contact_email, size, profile_photo_path, profile_photo_mime,
        OCTET_LENGTH(profile_photo_data) AS profile_photo_bytes,
        verified, logo_color, created_at';

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT ' . self::PROFILE_COLUMNS . ' FROM companies WHERE id=:id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function updateProfile(int $id, array $data): void
    {
        $sql = 'UPDATE companies SET name=:name,description=:description,city=:city,industry=:industry,website=:website,phone=:phone,contact_email=:contact_email,size=:size';
        if (array_key_exists('photo_path', $data)) {
            $sql .= ',profile_photo_path=:photo_path,profile_photo_mime=:photo_mime';
        }
        if (array_key_exists('photo_data', $data)) {
            $sql .= ',profile_photo_data=:photo_data';
        }
        $sql .= ' WHERE id=:id';
        $data['id'] = $id;
        unset($data['company']);
        Database::connection()->prepare($sql)->execute($data);
    }

    /**
     * Perfil corporativo visto por un candidato que se postuló a sus vacantes.
     *
     * La consulta solo trae datos de la empresa: nunca incluye el número de
     * documento ni los datos personales de la cuenta de la empresa.
     */
    public function findPublicProfile(int $companyId, int $candidateId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT companies.id, companies.name, companies.description, companies.city,
                    companies.industry, companies.website, companies.phone,
                    companies.contact_email, companies.size, companies.logo_color,
                    companies.profile_photo_path, companies.profile_photo_mime,
                    OCTET_LENGTH(companies.profile_photo_data) AS profile_photo_bytes,
                    companies.verified,
                    EXISTS(
                        SELECT 1
                        FROM applications a
                        JOIN jobs j ON j.id = a.job_id
                        WHERE j.company_id = companies.id
                          AND a.user_id = :candidate
                    ) AS related_candidate
             FROM companies
             WHERE companies.id = :company
             LIMIT 1'
        );

        $statement->execute([
            'company' => $companyId,
            'candidate' => $candidateId,
        ]);

        return $statement->fetch() ?: null;
    }

    public function currentPhoto(int $id): ?string
    {
        $statement = Database::connection()->prepare('SELECT profile_photo_path FROM companies WHERE id=:id');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() ?: null;
    }

    public function findPhoto(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT profile_photo_path, profile_photo_mime, profile_photo_data
             FROM companies
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }
}
