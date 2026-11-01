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
        $normalized = $this->validateAndNormalize($data);

        return $this->repository->create($companyId, $normalized);
    }

    public function byCompany(int $companyId): array
    {
        return $this->repository->findByCompany($companyId);
    }

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function update(int $id, int $companyId, array $data): void
    {
        if (!$this->repository->belongsToCompany($id, $companyId)) {
            throw new \RuntimeException('No tienes permisos para editar esta vacante o la vacante no existe.');
        }

        $normalized = $this->validateAndNormalize($data);

        $this->repository->update($id, $companyId, $normalized);
    }

    public function close(int $id, int $companyId): void
    {
        if (!$this->repository->belongsToCompany($id, $companyId)) {
            throw new \RuntimeException('No tienes permisos para cerrar esta vacante o la vacante no existe.');
        }

        $this->repository->updateStatus($id, $companyId, 'closed');
    }

    /**
     * Valida campos requeridos y que el rango salarial sea numérico, positivo y min <= max.
     */
    private function validateAndNormalize(array $data): array
    {
        foreach (['title', 'description', 'city', 'experience', 'salary_min', 'salary_max'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new \InvalidArgumentException('Completa todos los campos de la vacante.');
            }
        }

        $rawMin = trim((string) $data['salary_min']);
        $rawMax = trim((string) $data['salary_max']);

        if (!is_numeric($rawMin) || !is_numeric($rawMax)) {
            throw new \InvalidArgumentException('El salario mínimo y máximo deben ser valores numéricos.');
        }

        $salaryMin = (int) round((float) $rawMin);
        $salaryMax = (int) round((float) $rawMax);

        if ($salaryMin < 0 || $salaryMax < 0) {
            throw new \InvalidArgumentException('El salario no puede ser un valor negativo.');
        }

        if ($salaryMin > $salaryMax) {
            throw new \InvalidArgumentException('El salario mínimo no puede ser mayor que el salario máximo.');
        }

        $data['title'] = trim((string) $data['title']);
        $data['description'] = trim((string) $data['description']);
        $data['city'] = trim((string) $data['city']);
        $data['experience'] = trim((string) $data['experience']);
        $data['salary_min'] = $salaryMin;
        $data['salary_max'] = $salaryMax;
        $data['work_mode'] = ['hybrid' => 'Hybrid', 'remote' => 'Remote', 'onsite' => 'Presencial'][$data['work_mode'] ?? ''] ?? 'Hybrid';
        $data['status'] = ($data['status'] ?? '') === 'draft' ? 'draft' : 'published';

        return $data;
    }
}
