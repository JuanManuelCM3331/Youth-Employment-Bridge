<?php

declare(strict_types=1);

namespace Modules\Search\Services;

use Modules\Search\Repositories\JobInteractionRepository;

final class JobInteractionService
{
    public function __construct(private JobInteractionRepository $repository)
    {
    }

    public function toggleSaved(int $userId, int $jobId): void
    {
        if ($jobId < 1) {
            throw new \InvalidArgumentException('La vacante seleccionada no es válida.');
        }
        $this->repository->toggleSaved($userId, $jobId);
    }

    public function hide(int $userId, int $jobId): void
    {
        if ($jobId < 1) {
            throw new \InvalidArgumentException('La vacante seleccionada no es válida.');
        }
        $this->repository->hide($userId, $jobId);
    }

    public function hiddenJobIds(int $userId): array
    {
        return $this->repository->hiddenJobIds($userId);
    }

    public function savedJobIds(int $userId): array
    {
        return $this->repository->savedJobIds($userId);
    }

    public function savedJobs(int $userId): array
    {
        return $this->repository->savedJobs($userId);
    }
}
