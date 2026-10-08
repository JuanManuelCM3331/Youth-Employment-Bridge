<?php

namespace Modules\Users\Controllers;

use Modules\Users\Services\UserProfileService;

final class UserController
{
    public function __construct(
        private UserProfileService $users
    ) {
    }

    public function uploadCv(int $userId, array $file): void
    {
        $this->users->uploadCv($userId, $file);
    }

    public function profile(int $userId): ?array
    {
        return $this->users->find($userId);
    }

    public function updateCandidateProfile(
        int $userId,
        array $values,
        ?array $photo
    ): void {
        $this->users->updateCandidateProfile($userId, $values, $photo);
    }

    public function downloadCv(?array $user, int $candidateId): void
    {
        $file = $this->users->cvForDownload($user, $candidateId);

        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . filesize($file['path']));
        header(
            'Content-Disposition: attachment; filename="'
            . str_replace(['"', "\r", "\n"], '', $file['original_name'])
            . '"'
        );

        readfile($file['path']);
    }

    public function downloadProfilePhoto(?array $user, int $id): void
    {
        $photo = $this->users->profilePhotoForDownload($user, $id);

        header('Content-Type: ' . $photo['profile_photo_mime']);
        header('Content-Length: ' . filesize($photo['profile_photo_path']));
        header('Cache-Control: private, max-age=3600');

        readfile($photo['profile_photo_path']);
    }

    public function listForModeration(int $limit = 20, int $offset = 0): array
    {
        return $this->users->listForModeration($limit, $offset);
    }
}