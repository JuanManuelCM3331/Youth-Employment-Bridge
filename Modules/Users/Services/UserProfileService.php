<?php

namespace Modules\Users\Services;

use Modules\Auditoria\Services\AuditService;
use Modules\Users\Repositories\UserRepository;

final class UserProfileService
{
    public function __construct(
        private UserRepository $repository,
        private UserFileService $files,
        private AuditService $audit
    ) {
    }

    public function update(int $userId, array $data): void
    {
        $this->repository->updateProfile($userId, $data);
    }

    public function find(int $userId): ?array
    {
        return $this->repository->find($userId);
    }

    public function saveCv(int $userId, array $cv): ?string
    {
        return $this->repository->updateCv($userId, $cv);
    }

    public function uploadCv(int $userId, array $file): void
    {
        $cv = $this->files->storeCv($file);
        $oldPath = $this->repository->updateCv($userId, $cv);

        if ($oldPath && is_file($oldPath)) {
            @unlink($oldPath);
        }

        $this->audit->log(
            'cv_uploaded',
            'Users',
            'El candidato subió o reemplazó su hoja de vida.',
            'success',
            $userId,
            [
                'original_name' => $cv['original_name'],
                'size' => $cv['size'],
            ]
        );
    }

    public function updateCandidateProfile(
        int $userId,
        array $values,
        ?array $photo
    ): void {
        $oldPhoto = $this->repository->find($userId)['profile_photo_path'] ?? null;

        if ($photo) {
            $storedPhoto = $this->files->storeProfilePhoto($photo);
            $values['photo_path'] = $storedPhoto['path'];
            $values['photo_mime'] = $storedPhoto['mime'];
        }

        $this->repository->updateProfile($userId, $values);

        if ($photo && $oldPhoto && is_file($oldPhoto)) {
            @unlink($oldPhoto);
        }

        $this->audit->log(
            'candidate_profile_updated',
            'Users',
            'El candidato actualizó su perfil profesional.',
            'success',
            $userId,
            ['photo_updated' => (bool) $photo]
        );
    }

    public function cvForDownload(?array $user, int $candidateId): array
    {
        if (!$user || $candidateId < 1) {
            throw new \RuntimeException('Acceso no autorizado.', 403);
        }

        $candidate = $this->repository->findCandidateForDownload(
            $candidateId,
            (int) ($user['company_id'] ?? 0)
        );

        $allowed = $candidate
            && (
                $user['role'] === 'admin'
                || (int) $user['id'] === $candidateId
                || (
                    $user['role'] === 'recruiter'
                    && !empty($candidate['related_company'])
                )
            );

        if (!$allowed || !$candidate['cv_path'] || !is_file($candidate['cv_path'])) {
            throw new \RuntimeException('Hoja de vida no disponible.', 404);
        }

        $this->audit->log(
            'cv_downloaded',
            'Users',
            'Se descargó una hoja de vida autorizada.',
            'success',
            (int) $user['id'],
            ['candidate_id' => $candidateId]
        );

        return [
            'path' => $candidate['cv_path'],
            'mime' => $candidate['cv_mime'],
            'original_name' => $candidate['cv_original_name'],
        ];
    }

    /**
     * Perfil profesional de un candidato para la empresa que tiene su postulación.
     *
     * Reglas de privacidad:
     *  - Solo una empresa con una postulación activa del candidato puede consultarlo.
     *  - El número de documento y la imagen del reverso nunca forman parte del perfil.
     */
    public function candidatePublicProfile(?array $user, int $candidateId): array
    {
        if (!$user || $candidateId < 1) {
            throw new \RuntimeException('Acceso no autorizado.', 403);
        }

        if ($user['role'] !== 'recruiter' || empty($user['company_id'])) {
            throw new \RuntimeException('No tienes permisos para ver este perfil.', 403);
        }

        $candidate = $this->repository->findCandidatePublicProfile(
            $candidateId,
            (int) $user['company_id']
        );

        if (!$candidate || empty($candidate['related_company'])) {
            throw new \RuntimeException('Este candidato no se ha postulado a ninguna de tus vacantes.', 403);
        }

        unset(
            $candidate['cedula'],
            $candidate['password'],
            $candidate['id_scan_path'],
            $candidate['id_scan_mime'],
            $candidate['id_scan_source'],
            $candidate['id_scan_document'],
            $candidate['id_scan_data'],
            $candidate['id_scan_verified_at']
        );

        $this->audit->log(
            'candidate_profile_viewed',
            'Users',
            'Una empresa consultó el perfil profesional de un candidato postulado.',
            'success',
            (int) $user['id'],
            ['candidate_id' => $candidateId, 'company_id' => (int) $user['company_id']]
        );

        return $candidate;
    }

    /**
     * Indica si quien consulta puede ver la foto de perfil de un candidato.
     */
    private function canSeeCandidatePhoto(?array $user, int $candidateId, string $role = ''): bool
    {
        if (!$user || $candidateId < 1) {
            return false;
        }

        if ($user['role'] === 'admin' || (int) $user['id'] === $candidateId) {
            return true;
        }

        if ($user['role'] !== 'recruiter' || empty($user['company_id'])) {
            return false;
        }

        if ($role !== '' && $role !== 'candidate') {
            return false;
        }

        return $this->repository->candidateAppliedToCompany(
            $candidateId,
            (int) $user['company_id']
        );
    }

    public function profilePhotoForDownload(?array $user, int $id): array
    {
        if (!$user || $id < 1) {
            throw new \RuntimeException('Acceso no autorizado.', 403);
        }

        $photo = $this->repository->findProfilePhoto($id);

        if (!$photo) {
            throw new \RuntimeException('Foto no disponible.', 404);
        }

        $allowed = $this->canSeeCandidatePhoto($user, $id, (string) ($photo['role'] ?? ''));

        if (!$allowed || !$photo['profile_photo_path'] || !is_file($photo['profile_photo_path'])) {
            throw new \RuntimeException('Foto no disponible.', 404);
        }

        return $photo;
    }

    public function deleteCv(int $userId): void
    {
        $oldPath = $this->repository->deleteCv($userId);

        if ($oldPath && is_file($oldPath)) {
            @unlink($oldPath);
        }

        $this->audit->log('cv_deleted', 'Users', 'El candidato eliminó su hoja de vida.', 'success', $userId);
    }
}
