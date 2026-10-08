<?php

namespace Modules\Jobs\Services;

use Modules\Jobs\Repositories\JobManagementRepository;

final class JobManagementService
{
    public function __construct(private JobManagementRepository $repository)
    {
    }

    public function create(int $companyId, array $data): int
    {
        foreach (['title', 'description', 'city', 'experience', 'salary_min', 'salary_max'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') throw new \InvalidArgumentException('Completa todos los campos de la vacante.');
        }
        $data['work_mode'] = ['hybrid' => 'Hybrid', 'remote' => 'Remote', 'onsite' => 'Presencial'][$data['work_mode'] ?? ''] ?? 'Hybrid';
        $data['status'] = ($data['status'] ?? '') === 'draft' ? 'draft' : 'published';
        return $this->repository->create($companyId, $data);
    }

    public function byCompany(int $companyId): array
    {
        return $this->repository->findByCompany($companyId);
    }

    public function update(int $companyId, int $jobId, array $data): void
    {
        if ($jobId < 1) {
            throw new \InvalidArgumentException('Vacante inválida.');
        }

        foreach (['title', 'description', 'city', 'experience', 'salary_min', 'salary_max'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new \InvalidArgumentException('Completa todos los campos de la vacante.');
            }
        }

        $data['work_mode'] = ['hybrid' => 'Hybrid', 'remote' => 'Remote', 'onsite' => 'Presencial'][$data['work_mode'] ?? ''] ?? 'Hybrid';
        $data['status'] = in_array(($data['status'] ?? ''), ['draft', 'published', 'closed'], true) ? $data['status'] : 'published';
        $this->repository->update($companyId, $jobId, $data);
    }

    public function updateStatus(int $companyId, int $jobId, string $status): void
    {
        if (!in_array($status, ['draft', 'published', 'closed'], true)) {
            throw new \InvalidArgumentException('Estado de vacante inválido.');
        }

        $this->repository->updateStatus($companyId, $jobId, $status);
    }

    public function delete(int $companyId, int $jobId): void
    {
        if ($jobId < 1) {
            throw new \InvalidArgumentException('Vacante inválida.');
        }

        $this->repository->delete($companyId, $jobId);
    }
}
