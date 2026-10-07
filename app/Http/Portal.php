<?php

namespace App\Http;

use Modules\Applications\Controllers\ApplicationsController;
use Modules\Applications\Repositories\ApplicationRepository;
use Modules\Applications\Services\ApplicationService;
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

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
                throw new \RuntimeException('La sesión del formulario expiró. Recarga la página.');
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
                    foreach ($fields as $field) $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    $photo = (($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_photo'] : null;
                    $userController->updateCandidateProfile($user['id'], $values, $photo);
                } else {
                    $fields = ['name', 'description', 'city', 'industry', 'website', 'phone', 'contact_email', 'size'];
                    $values = [];
                    foreach ($fields as $field) $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
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

    private static function role(?array $user, string $role): void
    {
        if (!$user || $user['role'] !== $role || ($role === 'recruiter' && empty($user['company_id']))) {
            throw new \RuntimeException('No tienes permisos para realizar esta acción.');
        }
    }

    private static function redirect(string $location, ?string $message = null, ?string $error = null): void
    {
        if ($message !== null) $_SESSION['flash_message'] = $message;
        if ($error !== null) $_SESSION['flash_error'] = $error;
        header('Location: ' . $location);
        exit;
    }

    private static function safeReturn(string $location): string
    {
        return str_starts_with($location, '?action=') ? './' . $location : './?action=dashboard';
    }
}
