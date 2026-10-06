<?php

namespace Modules\Users\Controllers;

use Modules\Users\Services\UserProfileService;

final class UsersController
{
    public function __construct(private UserProfileService $profiles)
    {
    }

    public function update(int $userId, array $data): void
    {
        $this->profiles->update($userId, $data);
    }
}
