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

    public function profilePhotoForDownload(?array $user, int $id): array
    {
        if (!$user || $id < 1) {
            throw new \RuntimeException('Acceso no autorizado.', 403);
        }

        $photo = $this->repository->findProfilePhoto($id);
        $allowed = $user['role'] === 'admin' || (int) $user['id'] === $id;

        if (!$photo || !$allowed || !$photo['profile_photo_path'] || !is_file($photo['profile_photo_path'])) {
            throw new \RuntimeException('Foto no disponible.', 404);
        }

        return $photo;
    }
}
