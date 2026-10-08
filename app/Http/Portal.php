<?php

namespace App\Http;

use Modules\Applications\Controllers\ApplicationsController;
use Modules\Applications\Repositories\ApplicationRepository;
use Modules\Applications\Services\ApplicationService;
use Modules\Auditoria\Controllers\AuditController;
use Modules\Auditoria\Services\AuditService;
use Modules\Auth\Controllers\AuthController;
use Modules\Auth\Repositories\AuthRepository;
use Modules\Auth\Services\AuthService;
use Modules\Companies\Controllers\CompaniesController;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Companies\Services\CompanyFileService;
use Modules\Companies\Services\CompanyProfileService;
use Modules\Dashboard\Controllers\DashboardController;
use Modules\Dashboard\Repositories\DashboardRepository;
use Modules\Dashboard\Services\DashboardService;
use Modules\Jobs\Controllers\JobsController;
use Modules\Jobs\Repositories\JobManagementRepository;
use Modules\Jobs\Repositories\JobRepository;
use Modules\Jobs\Services\JobManagementService;
use Modules\Notifications\Controllers\NotificationsController;
use Modules\Notifications\Repositories\NotificationRepository;
use Modules\Notifications\Services\NotificationService;
use Modules\Search\Controllers\SearchController;
use Modules\Search\Repositories\JobInteractionRepository;
use Modules\Search\Services\JobInteractionService;
use Modules\Search\Services\SearchService;
use Modules\Users\Controllers\UserController;
use Modules\Users\Repositories\UserRepository;
use Modules\Users\Services\UserFileService;
use Modules\Users\Services\UserProfileService;
use Throwable;

final class Portal
{
    public static function run(): void
    {
        $action = $_GET['action'] ?? 'home';
        $user = $_SESSION['user'] ?? null;
        $error = $_SESSION['flash_error'] ?? null;
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_message']);

        $audit = new AuditService();
        $auditController = new AuditController($audit);
        $userFiles = new UserFileService();
        $users = new UserProfileService(new UserRepository(), $userFiles, $audit);
        $userController = new UserController($users);
        $companies = new CompanyProfileService(new CompanyRepository(), new CompanyFileService(), $audit);
        $companiesController = new CompaniesController($companies);
        $applications = new ApplicationService(new ApplicationRepository());
        $applicationsController = new ApplicationsController($applications);
        $jobsManager = new JobManagementService(new JobManagementRepository());
        $jobsController = new JobsController($jobsManager);
        $jobInteractions = new JobInteractionService(new JobInteractionRepository());
        $searchController = new SearchController(new SearchService(new JobRepository()), $jobInteractions);
        $notificationsController = new NotificationsController(new NotificationService(new NotificationRepository()));
        $dashboardController = new DashboardController(new DashboardService(new DashboardRepository()));
        $auth = new AuthController(new AuthService(new AuthRepository(), $audit));

        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $wantsJson = self::wantsJson();

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
                throw new \RuntimeException('La sesión del formulario expiró. Recarga la página.', 419);
            }

            if ($wantsJson) {
                self::handleJsonAction(
                    $action,
                    $user,
                    $audit,
                    $auditController,
                    $auth,
                    $userController,
                    $users,
                    $companies,
                    $companiesController,
                    $jobsController,
                    $applicationsController,
                    $searchController,
                    $dashboardController
                );
                return;
            }

            if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$auth->login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
                    $error = 'Correo o contraseña incorrectos.';
                } else {
                    header('Location: ./?action=dashboard');
                    exit;
                }
            } elseif ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $auth->register($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
                $message = 'Cuenta creada. Ya puedes ingresar.';
                $action = 'login';
            } elseif ($action === 'upload_cv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $userController->uploadCv($user['id'], $_FILES['cv'] ?? []);
                self::redirect('./?action=profile', 'Hoja de vida guardada correctamente.');
            } elseif ($action === 'save_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$user || !in_array($user['role'], ['candidate', 'recruiter'], true)) {
                    throw new \RuntimeException('No tienes permisos para editar este perfil.');
                }

                if ($user['role'] === 'candidate') {
                    $fields = ['name', 'phone', 'location', 'professional_title', 'bio', 'skills', 'experience', 'education', 'linkedin_url', 'portfolio_url', 'availability'];
                    $values = [];
                    foreach ($fields as $field) {
                        $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    }
                    $photo = (($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_photo'] : null;
                    $userController->updateCandidateProfile($user['id'], $values, $photo);
                } else {
                    $fields = ['name', 'description', 'city', 'industry', 'website', 'phone', 'contact_email', 'size'];
                    $values = [];
                    foreach ($fields as $field) {
                        $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    }
                    $photo = (($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_photo'] : null;
                    $companiesController->update($user['company_id'], $user['id'], $values, $photo);
                }

                self::redirect('./?action=' . ($user['role'] === 'candidate' ? 'profile' : 'company_profile'), 'Perfil actualizado correctamente.');
            } elseif ($action === 'download_profile_photo' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                if (($_GET['type'] ?? '') === 'user') {
                    $userController->downloadProfilePhoto($user, (int) ($_GET['id'] ?? 0));
                } else {
                    $companiesController->downloadPhoto($user, (int) ($_GET['id'] ?? 0));
                }
                exit;
            } elseif ($action === 'download_cv' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $userController->downloadCv($user, (int) ($_GET['candidate'] ?? 0));
                exit;
            } elseif ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $auth->logout($user);
                header('Location: ./');
                exit;
            } elseif ($action === 'create_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                $jobId = $jobsController->create($user['company_id'], $_POST);
                $audit->log('job_created', 'Jobs', 'La empresa creó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::redirect('./?action=company_jobs', 'Vacante creada correctamente.');
            } elseif ($action === 'apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $applicationsController->apply($jobId, $user['id']);
                $audit->log('application_created', 'Applications', 'El candidato envió una postulación.', 'success', $user['id'], ['job_id' => $jobId]);
                self::redirect('./?action=dashboard', 'Postulación enviada correctamente.');
            } elseif ($action === 'toggle_saved_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $searchController->toggleSaved($user['id'], $jobId);
                $audit->log('job_saved_toggled', 'Search', 'El candidato actualizó una vacante guardada.', 'success', $user['id'], ['job_id' => $jobId]);
                self::redirect(self::safeReturn($_POST['return_to'] ?? ''), 'Favoritos actualizados.');
            } elseif ($action === 'hide_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $searchController->hide($user['id'], $jobId);
                $audit->log('job_hidden', 'Search', 'El candidato ocultó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::redirect(self::safeReturn($_POST['return_to'] ?? ''), 'Vacante ocultada.');
            } elseif ($action === 'update_application' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                $applicationsController->updateStatus((int) $_POST['application_id'], $user['company_id'], $_POST['status']);
                $audit->log('application_status_updated', 'Applications', 'La empresa actualizó una postulación.', 'success', $user['id'], ['application_id' => (int) $_POST['application_id'], 'status' => $_POST['status']]);
                self::redirect('./?action=company_applications', 'Estado actualizado.');
            }
        } catch (Throwable $exception) {
            if ($wantsJson) {
                self::jsonResponse(
                    false,
                    $exception->getMessage(),
                    [],
                    ['exception' => $exception->getMessage()],
                    self::statusFromException($exception)
                );
                return;
            }

            $error = $exception->getMessage();
            if ($user) {
                $audit->log('action_error', 'System', 'Acción rechazada: ' . substr($exception->getMessage(), 0, 180), 'error', $user['id'], ['action' => $action]);
            }
        }

        $jobs = $searchController->jobs($_GET['q'] ?? '', $_GET['location'] ?? '');

        if ($user && $user['role'] === 'candidate' && $action === 'dashboard') {
            $content = PortalViewRenderer::candidateArea($user, $message, $error, $jobs, $userController, $applicationsController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'profile') {
            $content = PortalViewRenderer::candidateProfile($user, $message, $error, $userController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'applications') {
            $content = PortalViewRenderer::candidateApplications($user, $message, $error, $applicationsController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'favorites') {
            $content = PortalViewRenderer::candidateFavorites($user, $message, $error, $searchController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'alerts') {
            $content = PortalViewRenderer::candidateAlerts($user, $message, $error, $notificationsController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'search') {
            $content = PortalViewRenderer::candidateDashboard($user, $message, $error, $jobs, $searchController);
        } elseif ($user && $user['role'] === 'recruiter' && in_array($action, ['dashboard', 'company_profile', 'company_jobs', 'company_applications'], true)) {
            $content = PortalViewRenderer::companyArea($user, $action, $message, $error, $companies, $jobsController, $applicationsController);
        } else {
            $content = ($action === 'dashboard' && $user)
                ? PortalViewRenderer::dashboard($user, $message, $error, $jobs, $dashboardController, $searchController)
                : PortalViewRenderer::home($jobs, $user, $action, $message, $error);
        }

        echo PortalViewRenderer::layout($content, $user);
    }

    private static function handleJsonAction(
        string $action,
        ?array $user,
        AuditService $audit,
        AuditController $auditController,
        AuthController $auth,
        UserController $userController,
        UserProfileService $users,
        CompanyProfileService $companies,
        CompaniesController $companiesController,
        JobsController $jobsController,
        ApplicationsController $applicationsController,
        SearchController $searchController,
        DashboardController $dashboardController
    ): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        switch ($action) {
            case 'logout':
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $auth->logout($user);
                self::jsonResponse(true, 'Sesión finalizada correctamente.');
                return;

            case 'search_jobs':
                $filters = self::cleanSearchFilters($_GET);
                $data = $searchController->jobsPaginated(
                    $filters,
                    max(1, (int) ($_GET['page'] ?? 1)),
                    max(1, (int) ($_GET['per_page'] ?? 10))
                );
                if ($user && $user['role'] === 'candidate') {
                    $data['saved_ids'] = $searchController->savedJobIds($user['id']);
                    $data['hidden_ids'] = $searchController->hiddenJobIds($user['id']);
                }
                self::jsonResponse(true, 'Resultados obtenidos.', $data);
                return;

            case 'saved_jobs':
                self::role($user, 'candidate');
                self::jsonResponse(true, 'Favoritos obtenidos.', ['items' => $searchController->savedJobs($user['id'])]);
                return;

            case 'toggle_saved_job':
                self::role($user, 'candidate');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $searchController->toggleSaved($user['id'], $jobId);
                $audit->log('job_saved_toggled', 'Search', 'El candidato actualizó una vacante guardada.', 'success', $user['id'], ['job_id' => $jobId]);
                self::jsonResponse(true, 'Favoritos actualizados.');
                return;

            case 'hide_job':
                self::role($user, 'candidate');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $searchController->hide($user['id'], $jobId);
                $audit->log('job_hidden', 'Search', 'El candidato ocultó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::jsonResponse(true, 'Vacante ocultada.');
                return;

            case 'apply':
                self::role($user, 'candidate');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $applicationsController->apply($jobId, $user['id']);
                $audit->log('application_created', 'Applications', 'El candidato envió una postulación.', 'success', $user['id'], ['job_id' => $jobId]);
                self::jsonResponse(true, 'Postulación enviada correctamente.');
                return;

            case 'company_jobs':
                self::role($user, 'recruiter');
                self::jsonResponse(true, 'Vacantes obtenidas.', ['items' => $jobsController->byCompany((int) $user['company_id'])]);
                return;

            case 'create_job':
                self::role($user, 'recruiter');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $jobId = $jobsController->create((int) $user['company_id'], $_POST);
                $audit->log('job_created', 'Jobs', 'La empresa creó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::jsonResponse(true, 'Vacante creada correctamente.', ['job_id' => $jobId]);
                return;

            case 'update_job':
                self::role($user, 'recruiter');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $jobsController->update((int) $user['company_id'], $jobId, $_POST);
                $audit->log('job_updated', 'Jobs', 'La empresa actualizó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::jsonResponse(true, 'Vacante actualizada correctamente.');
                return;

            case 'update_job_status':
                self::role($user, 'recruiter');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $status = (string) ($_POST['status'] ?? '');
                $jobsController->updateStatus((int) $user['company_id'], $jobId, $status);
                $audit->log('job_status_updated', 'Jobs', 'La empresa cambió el estado de una vacante.', 'success', $user['id'], ['job_id' => $jobId, 'status' => $status]);
                self::jsonResponse(true, 'Estado de vacante actualizado.');
                return;

            case 'delete_job':
                self::role($user, 'recruiter');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $jobsController->delete((int) $user['company_id'], $jobId);
                $audit->log('job_deleted', 'Jobs', 'La empresa eliminó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::jsonResponse(true, 'Vacante eliminada correctamente.');
                return;

            case 'company_applications':
                self::role($user, 'recruiter');
                $items = $applicationsController->byCompany((int) $user['company_id']);
                $counts = ['pending' => 0, 'review' => 0, 'interview' => 0, 'accepted' => 0, 'rejected' => 0];
                foreach ($items as $item) {
                    $status = $item['status'];
                    if (isset($counts[$status])) {
                        $counts[$status]++;
                    }
                }
                self::jsonResponse(true, 'Postulaciones obtenidas.', ['items' => $items, 'counts' => $counts]);
                return;

            case 'update_application':
                self::role($user, 'recruiter');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $applicationId = (int) ($_POST['application_id'] ?? 0);
                $status = (string) ($_POST['status'] ?? '');
                $applicationsController->updateStatus($applicationId, (int) $user['company_id'], $status);
                $audit->log('application_status_updated', 'Applications', 'La empresa actualizó una postulación.', 'success', $user['id'], ['application_id' => $applicationId, 'status' => $status]);
                self::jsonResponse(true, 'Estado actualizado.', ['application_id' => $applicationId, 'status' => $status]);
                return;

            case 'company_profile':
                self::role($user, 'recruiter');
                self::jsonResponse(true, 'Perfil de empresa obtenido.', ['company' => $companies->find((int) $user['company_id'])]);
                return;

            case 'save_profile':
                if (!$user || !in_array($user['role'], ['candidate', 'recruiter'], true)) {
                    throw new \RuntimeException('No tienes permisos para editar este perfil.', 403);
                }
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }

                if ($user['role'] === 'candidate') {
                    $fields = ['name', 'phone', 'location', 'professional_title', 'bio', 'skills', 'experience', 'education', 'linkedin_url', 'portfolio_url', 'availability'];
                    $values = [];
                    foreach ($fields as $field) {
                        $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    }
                    $photo = (($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_photo'] : null;
                    $userController->updateCandidateProfile((int) $user['id'], $values, $photo);
                } else {
                    $fields = ['name', 'description', 'city', 'industry', 'website', 'phone', 'contact_email', 'size'];
                    $values = [];
                    foreach ($fields as $field) {
                        $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    }
                    $photo = (($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_photo'] : null;
                    $companiesController->update((int) $user['company_id'], (int) $user['id'], $values, $photo);
                }

                self::jsonResponse(true, 'Perfil actualizado correctamente.');
                return;

            case 'upload_cv':
                self::role($user, 'candidate');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $userController->uploadCv((int) $user['id'], $_FILES['cv'] ?? []);
                self::jsonResponse(true, 'Hoja de vida guardada correctamente.');
                return;

            case 'dashboard_summary':
                self::role($user, 'admin');
                self::jsonResponse(true, 'Resumen cargado.', $dashboardController->summary());
                return;

            case 'audit_logs':
                self::role($user, 'admin');
                self::jsonResponse(
                    true,
                    'Auditoría cargada.',
                    $auditController->logs(
                        [
                            'module' => trim((string) ($_GET['module'] ?? '')),
                            'status' => trim((string) ($_GET['status'] ?? '')),
                        ],
                        max(1, (int) ($_GET['page'] ?? 1)),
                        max(1, (int) ($_GET['per_page'] ?? 20))
                    )
                );
                return;

            case 'companies_moderation':
                self::role($user, 'admin');
                $verified = $_GET['verified'] ?? null;
                $verifiedFilter = ($verified === '0' || $verified === '1') ? (int) $verified : null;
                self::jsonResponse(
                    true,
                    'Empresas cargadas.',
                    [
                        'items' => $companiesController->listForModeration(
                            max(1, (int) ($_GET['limit'] ?? 20)),
                            max(0, (int) ($_GET['offset'] ?? 0)),
                            $verifiedFilter
                        ),
                    ]
                );
                return;

            case 'set_company_verification':
                self::role($user, 'admin');
                if ($method !== 'POST') {
                    throw new \RuntimeException('Método no permitido.', 405);
                }
                $companyId = (int) ($_POST['company_id'] ?? 0);
                $verified = (int) ($_POST['verified'] ?? 0) === 1;
                $companiesController->setVerified($companyId, $verified);
                $audit->log('company_verification_updated', 'Companies', 'Administrador actualizó verificación de empresa.', 'success', $user['id'], ['company_id' => $companyId, 'verified' => $verified]);
                self::jsonResponse(true, 'Estado de empresa actualizado.');
                return;

            case 'users_moderation':
                self::role($user, 'admin');
                self::jsonResponse(
                    true,
                    'Usuarios cargados.',
                    [
                        'items' => $userController->listForModeration(
                            max(1, (int) ($_GET['limit'] ?? 20)),
                            max(0, (int) ($_GET['offset'] ?? 0))
                        ),
                        'moderation_actions_enabled' => false,
                    ]
                );
                return;

            case 'moderate_user':
                self::role($user, 'admin');
                throw new \RuntimeException('La moderación de usuarios está deshabilitada porque no existe un campo de suspensión en el esquema actual.', 422);
        }

        throw new \RuntimeException('Acción AJAX no soportada.', 404);
    }

    private static function cleanSearchFilters(array $input): array
    {
        $filters = [
            'q' => trim((string) ($input['q'] ?? '')),
            'location' => trim((string) ($input['location'] ?? '')),
            'work_mode' => trim((string) ($input['work_mode'] ?? '')),
            'experience' => trim((string) ($input['experience'] ?? '')),
        ];

        foreach (['salary_min', 'salary_max'] as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            $filters[$field] = ($value !== '' && is_numeric($value) && (float) $value >= 0) ? (float) $value : null;
        }

        if ($filters['salary_min'] !== null && $filters['salary_max'] !== null && $filters['salary_min'] > $filters['salary_max']) {
            [$filters['salary_min'], $filters['salary_max']] = [$filters['salary_max'], $filters['salary_min']];
        }

        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                unset($filters[$key]);
            }
        }

        return $filters;
    }

    private static function wantsJson(): bool
    {
        return (($_GET['format'] ?? '') === 'json')
            || (($_GET['ajax'] ?? '') === '1')
            || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    private static function statusFromException(Throwable $exception): int
    {
        $code = (int) $exception->getCode();
        if ($code >= 400 && $code <= 599) {
            return $code;
        }

        if ($exception instanceof \InvalidArgumentException) {
            return 422;
        }

        return 500;
    }

    private static function jsonResponse(bool $ok, string $message, array $data = [], array $errors = [], int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $ok,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function role(?array $user, string $role): void
    {
        if (!$user || $user['role'] !== $role || ($role === 'recruiter' && empty($user['company_id']))) {
            throw new \RuntimeException('No tienes permisos para realizar esta acción.', 403);
        }
    }

    private static function redirect(string $location, ?string $message = null, ?string $error = null): void
    {
        if ($message !== null) {
            $_SESSION['flash_message'] = $message;
        }
        if ($error !== null) {
            $_SESSION['flash_error'] = $error;
        }
        header('Location: ' . $location);
        exit;
    }

    private static function safeReturn(string $location): string
    {
        return str_starts_with($location, '?action=') ? './' . $location : './?action=dashboard';
    }
}
