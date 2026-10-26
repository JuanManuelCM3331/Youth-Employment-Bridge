<?php

namespace Modules\Dashboard\Services;

use App\Core\Database;
use InvalidArgumentException;
use Modules\Auditoria\Services\AuditService;
use Modules\Dashboard\Repositories\DashboardRepository;
use RuntimeException;

final class DashboardService
{
    public function __construct(
        private DashboardRepository $repository,
        private ?AuditService $audit = null
    ) {
        $this->audit ??= new AuditService();
    }

    public function summary(array $filters = []): array
    {
        return [
            'counts' => $this->repository->counts(),
            'logs' => $this->repository->auditLogs($filters),
            'users' => $this->repository->usersList(),
            'minors' => $this->repository->minorsList(),
            'companies' => $this->repository->companiesList(),
            'jobs' => $this->repository->jobsList(),
            'applications' => $this->repository->applicationsList(),
            'modules' => $this->repository->distinctModules(),
            'actions' => $this->repository->distinctActions(),
            'filters' => $filters,
        ];
    }

    public function minorsList(): array
    {
        return $this->repository->minorsList();
    }

    public function unblockMinor(int $adminId, int $targetUserId): void
    {
        $user = $this->repository->findUser($targetUserId);
        if (!$user) {
            throw new RuntimeException('El usuario que intentas desbloquear no existe.');
        }

        $this->repository->setMinorBlocked($targetUserId, 0);

        try {
            $stmt = Database::connection()->prepare(
                'UPDATE user_minor_documents SET approved_at = NOW(), approved_by = :admin_id WHERE user_id = :id'
            );
            $stmt->execute(['admin_id' => $adminId, 'id' => $targetUserId]);
        } catch (\Throwable) {
        }

        $this->audit->log(
            'minor_unblocked',
            'Users',
            "El administrador retiró el bloqueo al usuario menor de edad: {$user['name']} ({$user['email']}).",
            'success',
            $adminId,
            ['unblocked_user_id' => $targetUserId, 'email' => $user['email']]
        );
    }

    public function blockMinor(int $adminId, int $targetUserId): void
    {
        $user = $this->repository->findUser($targetUserId);
        if (!$user) {
            throw new RuntimeException('El usuario que intentas bloquear no existe.');
        }

        $this->repository->setMinorBlocked($targetUserId, 1);

        $this->audit->log(
            'minor_blocked',
            'Users',
            "El administrador activó el bloqueo al usuario menor de edad: {$user['name']} ({$user['email']}).",
            'success',
            $adminId,
            ['blocked_user_id' => $targetUserId, 'email' => $user['email']]
        );
    }

    public function deleteJob(int $adminId, int $jobId): void
    {
        $job = $this->repository->findJob($jobId);
        if (!$job) {
            throw new RuntimeException('La vacante que intentas eliminar no existe.');
        }

        $this->repository->deleteJob($jobId);

        $this->audit->log(
            'job_deleted',
            'Jobs',
            "El administrador eliminó la vacante: {$job['title']} (ID: {$jobId}, Empresa: {$job['company_name']}).",
            'success',
            $adminId,
            ['deleted_job_id' => $jobId, 'title' => $job['title'], 'company_id' => $job['company_id']]
        );
    }

    public function deleteApplication(int $adminId, int $applicationId): void
    {
        $application = $this->repository->findApplication($applicationId);
        if (!$application) {
            throw new RuntimeException('La postulación que intentas eliminar no existe.');
        }

        $this->repository->deleteApplication($applicationId);

        $this->audit->log(
            'application_deleted',
            'Applications',
            "El administrador eliminó la postulación #{$applicationId} de {$application['candidate_name']} a la vacante {$application['job_title']}.",
            'success',
            $adminId,
            [
                'deleted_application_id' => $applicationId,
                'candidate_id' => $application['user_id'],
                'job_id' => $application['job_id'],
            ]
        );
    }

    public function deleteUser(int $adminId, int $targetUserId): void
    {
        if ($adminId === $targetUserId) {
            throw new RuntimeException('No puedes eliminar tu propia cuenta de administrador.');
        }

        $user = $this->repository->findUser($targetUserId);
        if (!$user) {
            throw new RuntimeException('El usuario que intentas eliminar no existe.');
        }

        $this->repository->deleteUser($targetUserId);

        $this->audit->log(
            'user_deleted',
            'Users',
            "El administrador eliminó al usuario: {$user['name']} ({$user['email']}, Rol: {$user['role']}).",
            'success',
            $adminId,
            ['deleted_user_id' => $targetUserId, 'email' => $user['email'], 'role' => $user['role']]
        );
    }

    public function suspendUser(int $adminId, int $targetUserId): void
    {
        if ($adminId === $targetUserId) {
            throw new RuntimeException('No puedes suspender tu propia cuenta de administrador.');
        }

        $user = $this->repository->findUser($targetUserId);
        if (!$user) {
            throw new RuntimeException('El usuario que intentas suspender no existe.');
        }

        $this->repository->setUserStatus($targetUserId, 'suspended');

        $this->audit->log(
            'user_suspended',
            'Users',
            "El administrador suspendió al usuario: {$user['name']} ({$user['email']}, Rol: {$user['role']}).",
            'success',
            $adminId,
            ['suspended_user_id' => $targetUserId, 'email' => $user['email'], 'role' => $user['role']]
        );
    }

    public function activateUser(int $adminId, int $targetUserId): void
    {
        $user = $this->repository->findUser($targetUserId);
        if (!$user) {
            throw new RuntimeException('El usuario que intentas activar no existe.');
        }

        $this->repository->setUserStatus($targetUserId, 'active');

        $this->audit->log(
            'user_activated',
            'Users',
            "El administrador activó al usuario: {$user['name']} ({$user['email']}, Rol: {$user['role']}).",
            'success',
            $adminId,
            ['activated_user_id' => $targetUserId, 'email' => $user['email'], 'role' => $user['role']]
        );
    }

    public function deleteCompany(int $adminId, int $companyId): void
    {
        $company = $this->repository->findCompany($companyId);
        if (!$company) {
            throw new RuntimeException('La empresa que intentas eliminar no existe.');
        }

        $this->repository->deleteCompany($companyId);

        $this->audit->log(
            'company_deleted',
            'Companies',
            "El administrador eliminó la empresa: {$company['name']} (ID: {$companyId}, Ciudad: {$company['city']}).",
            'success',
            $adminId,
            ['deleted_company_id' => $companyId, 'name' => $company['name']]
        );
    }

    public function createAdmin(int $adminId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('El nombre del administrador es obligatorio.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico no tiene un formato válido.');
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe contener al menos 6 caracteres.');
        }

        if ($this->repository->emailExists($email)) {
            throw new RuntimeException('Ya existe una cuenta registrada con este correo electrónico.');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $newAdminId = $this->repository->createAdmin($name, $email, $hashedPassword);

        $this->audit->log(
            'admin_created',
            'Auth',
            "Se creó un nuevo usuario administrador: {$name} ({$email}).",
            'success',
            $adminId,
            ['new_admin_id' => $newAdminId, 'email' => $email]
        );

        return $newAdminId;
    }
}
