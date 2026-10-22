<?php

namespace Modules\Companies\Services;

use Modules\Auditoria\Services\AuditService;
use Modules\Companies\Repositories\CompanyRepository;

final class CompanyProfileService
{
    public function __construct(
        private CompanyRepository $repository,
        private CompanyFileService $files,
        private AuditService $audit
    ) {
    }

    public function update(int $companyId, int $userId, array $data, ?array $photo): void
    {
        $oldPhoto = $this->repository->currentPhoto($companyId);

        if ($photo) {
            $storedPhoto = $this->files->storeProfilePhoto($photo);
            $data['photo_path'] = $storedPhoto['path'];
            $data['photo_mime'] = $storedPhoto['mime'];
        }

        $this->repository->updateProfile($companyId, $data);

        if ($photo && $oldPhoto && is_file($oldPhoto)) {
            @unlink($oldPhoto);
        }

        $this->audit->log(
            'company_profile_updated',
            'Companies',
            'La empresa actualizó su perfil corporativo.',
            'success',
            $userId,
            ['company_id' => $companyId, 'photo_updated' => (bool) $photo]
        );
    }

    public function find(int $companyId): ?array
    {
        return $this->repository->find($companyId);
    }

    public function currentPhoto(int $companyId): ?string
    {
        return $this->repository->currentPhoto($companyId);
    }

    /**
     * Perfil corporativo visto por un candidato que se postuló a sus vacantes.
     *
     * Reglas de privacidad:
     *  - Solo un candidato con postulación activa en la empresa puede consultarlo.
     *  - Nunca se expone el número de documento de la cuenta de la empresa.
     */
    public function publicProfile(?array $user, int $companyId): array
    {
        if (!$user || $companyId < 1) {
            throw new \RuntimeException('Acceso no autorizado.', 403);
        }

        if ($user['role'] !== 'candidate') {
            throw new \RuntimeException('No tienes permisos para ver este perfil.', 403);
        }

        $company = $this->repository->findPublicProfile($companyId, (int) $user['id']);

        if (!$company || empty($company['related_candidate'])) {
            throw new \RuntimeException('No te has postulado a ninguna vacante de esta empresa.', 403);
        }

        $this->audit->log(
            'company_profile_viewed',
            'Companies',
            'Un candidato consultó el perfil corporativo de una empresa a la que se postuló.',
            'success',
            (int) $user['id'],
            ['company_id' => $companyId]
        );

        return $company;
    }

    public function photoForDownload(?array $user, int $companyId): array
    {
        if (!$user || $companyId < 1) {
            throw new \RuntimeException('Acceso no autorizado.', 403);
        }

        $company = $this->repository->find($companyId);
        $allowed = $user !== null;

        if (!$company || !$allowed || !$company['profile_photo_path'] || !is_file($company['profile_photo_path'])) {
            throw new \RuntimeException('Foto no disponible.', 404);
        }

        return [
            'path' => $company['profile_photo_path'],
            'mime' => $company['profile_photo_mime'],
        ];
    }
}
