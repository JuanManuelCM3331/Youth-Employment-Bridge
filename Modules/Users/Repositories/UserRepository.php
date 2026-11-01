<?php

namespace Modules\Users\Repositories;

use App\Core\Database;

final class UserRepository
{
    /**
     * Columnas de perfil sin los BLOB de archivos. Se calcula su tamaño para
     * que las vistas puedan saber si existe foto o CV sin traer el binario.
     */
    private const PROFILE_COLUMNS = 'id, name, email, cedula, password, role, is_minor, company_id,
        bio, phone, location, professional_title, skills, experience, education, linkedin_url,
        portfolio_url, availability, profile_photo_path, profile_photo_mime,
        OCTET_LENGTH(profile_photo_data) AS profile_photo_bytes,
        cv_path, cv_original_name, cv_mime, cv_size, cv_uploaded_at,
        OCTET_LENGTH(cv_data) AS cv_bytes,
        id_scan_path, id_scan_mime, id_scan_source, id_scan_document, id_scan_data,
        id_scan_verified_at, created_at, status, minor_blocked, birth_date';

    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT ' . self::PROFILE_COLUMNS . ' FROM users WHERE id=:id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function updateProfile(int $id, array $data): void
    {
        $sql = 'UPDATE users SET name=:name,phone=:phone,location=:location,professional_title=:professional_title,bio=:bio,skills=:skills,experience=:experience,education=:education,linkedin_url=:linkedin_url,portfolio_url=:portfolio_url,availability=:availability';
        if (array_key_exists('photo_path', $data)) {
            $sql .= ',profile_photo_path=:photo_path,profile_photo_mime=:photo_mime';
        }
        if (array_key_exists('photo_data', $data)) {
            $sql .= ',profile_photo_data=:photo_data';
        }
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
        Database::connection()->prepare(
            'UPDATE users SET cv_path=:path,cv_data=:data,cv_original_name=:original_name,cv_mime=:mime,cv_size=:size,cv_uploaded_at=NOW() WHERE id=:id'
        )->execute([
            'path' => $data['path'],
            'data' => $data['data'] ?? null,
            'original_name' => $data['original_name'],
            'mime' => $data['mime'],
            'size' => $data['size'],
            'id' => $id,
        ]);
        return $oldPath;
    }

    public function findCandidateForDownload(int $candidateId, int $companyId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT users.id, users.role, users.company_id,
                    users.cv_path, users.cv_original_name, users.cv_mime, users.cv_data,
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

    /**
     * Perfil profesional de un candidato visto por la empresa a la que se postuló.
     *
     * La consulta NO trae el número de documento ni ninguna referencia al
     * reverso del documento de identidad: ese material es privado del titular
     * y de la administración del portal.
     */
    public function findCandidatePublicProfile(int $candidateId, int $companyId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT users.id, users.name, users.email, users.professional_title, users.phone,
                    users.location, users.availability, users.bio, users.skills, users.experience,
                    users.education, users.linkedin_url, users.portfolio_url,
                    users.profile_photo_path, users.profile_photo_mime,
                    OCTET_LENGTH(users.profile_photo_data) AS profile_photo_bytes,
                    users.cv_path, users.cv_original_name, users.cv_mime,
                    OCTET_LENGTH(users.cv_data) AS cv_bytes,
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

    /**
     * Indica si el candidato tiene alguna postulación en las vacantes de la empresa.
     */
    public function candidateAppliedToCompany(int $candidateId, int $companyId): bool
    {
        if ($candidateId < 1 || $companyId < 1) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             WHERE a.user_id = :candidate
               AND j.company_id = :company'
        );

        $statement->execute([
            'candidate' => $candidateId,
            'company' => $companyId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function findProfilePhoto(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT profile_photo_path, profile_photo_mime, profile_photo_data, role
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function deleteCv(int $id): ?string
    {
        $statement = Database::connection()->prepare('SELECT cv_path FROM users WHERE id=:id');
        $statement->execute(['id' => $id]);
        $oldPath = $statement->fetchColumn() ?: null;
        Database::connection()->prepare(
            'UPDATE users SET cv_path=NULL, cv_data=NULL, cv_original_name=NULL, cv_mime=NULL, cv_size=NULL, cv_uploaded_at=NULL WHERE id=:id'
        )->execute(['id' => $id]);
        return $oldPath;
    }
}
