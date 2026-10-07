<?php

namespace Modules\Users\Services;

use Modules\Users\Repositories\UserRepository;

final class UserProfileService
{
    public function __construct(private UserRepository $repository)
    {
    }

    public function update(int $userId, array $data): void
    {
        $this->repository->updateProfile($userId, $data);
    }

    public function saveCv(int $userId, array $cv): ?string
    {
        return $this->repository->updateCv($userId, $cv);
    }
}
