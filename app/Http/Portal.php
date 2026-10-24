<?php

namespace App\Http;

use Modules\Applications\Controllers\ApplicationsController;
use Modules\Applications\Repositories\ApplicationRepository;
use Modules\Applications\Services\ApplicationService;
use Modules\Auditoria\Services\AuditService;
use Modules\Auth\Controllers\AuthController;
use Modules\Auth\Repositories\AuthRepository;
use Modules\Auth\Services\AuthService;
use Modules\Chat\Controllers\ChatController;
use Modules\Chat\Repositories\ChatRepository;
use Modules\Chat\Services\ChatService;
use Modules\Companies\Controllers\CompaniesController;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Companies\Services\CompanyFileService;
use Modules\Companies\Services\CompanyProfileService;
use Modules\Dashboard\Controllers\DashboardController;
use Modules\Dashboard\Repositories\DashboardRepository;
use Modules\Dashboard\Services\DashboardService;
use Modules\Identity\Dto\CedulaConfig;
use Modules\Identity\Dto\IdScanConfig;
use Modules\Identity\Controllers\IdScanController;
use Modules\Identity\Repositories\IdScanRepository;
use Modules\Identity\Services\CedulaProviderFactory;
use Modules\Identity\Services\CedulaValidationService;
use Modules\Identity\Services\IdScanService;
use Modules\Identity\Services\IdScanStorage;
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
        $dashboardController = new DashboardController(new DashboardService(new DashboardRepository(), $audit));
        $cedulaConfig = CedulaConfig::fromArray(require __DIR__ . '/../../config/cedula.php');
        $cedulaService = new CedulaValidationService(
            CedulaProviderFactory::make($cedulaConfig),
            $cedulaConfig
        );
        $idScanConfig = IdScanConfig::fromArray(require __DIR__ . '/../../config/id_scan.php');
        $idScanStorage = new IdScanStorage($idScanConfig);
        $authRepository = new AuthRepository();
        $auth = new AuthController(new AuthService(
            $authRepository,
            $audit,
            $cedulaService,
            new IdScanService($idScanConfig),
            $idScanStorage
        ));
        $idScans = new IdScanController($idScanStorage, new IdScanRepository());
        $chatRepository = new ChatRepository();
        $chatService = new ChatService($chatRepository, new NotificationService(new NotificationRepository()), $audit);
        $chatController = new ChatController($chatService);

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
                $auth->register(
                    $_POST['name'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['password'] ?? '',
                    $_POST['password_confirm'] ?? '',
                    $_POST['cedula'] ?? '',
                    $_POST['birth_date'] ?? '',
                    [
                        'data' => $_POST['id_scan'] ?? null,
                        'file' => $_FILES['id_scan_image'] ?? null,
                    ]
                );
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
                    if (empty($user['company_id'])) {
                        throw new \RuntimeException('Tu cuenta no está asociada a ninguna empresa.');
                    }
                    $fields = ['name', 'description', 'city', 'industry', 'website', 'phone', 'contact_email', 'size'];
                    $values = [];
                    foreach ($fields as $field) $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    $photo = (($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $_FILES['profile_photo'] : null;
                    $companiesController->update((int) $user['company_id'], $user['id'], $values, $photo);
                }

                $freshUser = $userController->profile($user['id']);
                if ($freshUser) {
                    $_SESSION['user']['name'] = $freshUser['name'];
                    $user = $_SESSION['user'];
                }

                self::redirect('./?action=' . ($user['role'] === 'candidate' ? 'profile' : 'company_profile'), 'Perfil actualizado correctamente.');
            } elseif ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$user || !in_array($user['role'], ['candidate', 'recruiter'], true)) {
                    throw new \RuntimeException('No tienes permisos para cambiar la contraseña.');
                }

                $auth->changePassword(
                    (int) $user['id'],
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['password_confirm'] ?? '')
                );

                self::redirect(
                    './?action=' . ($user['role'] === 'candidate' ? 'profile' : 'company_profile'),
                    'Contraseña actualizada correctamente.'
                );
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
            } elseif ($action === 'download_id_scan' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $ownerId = (int) ($_GET['candidate'] ?? ($user['id'] ?? 0));
                $idScans->download($user, $ownerId);
                exit;
            } elseif ($action === 'candidate_public_profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                self::role($user, 'recruiter');
                $candidateProfile = $userController->publicProfile($user, (int) ($_GET['candidate'] ?? 0));
            } elseif ($action === 'company_public_profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                self::role($user, 'candidate');
                $companyProfile = $companiesController->publicProfile($user, (int) ($_GET['company'] ?? 0));
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
                $notificationsController->create($user['id'], 'Tu postulación ha sido enviada correctamente.');
                self::redirect(self::safeReturn($_POST['return_to'] ?? ''), 'Postulación enviada correctamente.');
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
            } elseif ($action === 'register_company' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $auth->registerCompany(
                    $_POST['name'] ?? '',
                    $_POST['email'] ?? '',
                    $_POST['password'] ?? '',
                    $_POST['password_confirm'] ?? '',
                    $_POST['company_name'] ?? '',
                    $_POST['city'] ?? '',
                    $_POST['cedula'] ?? ''
                );
                $message = 'Empresa registrada exitosamente. Ya puedes ingresar.';
                $action = 'login';
            } elseif ($action === 'edit_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $jobsController->update($jobId, $user['company_id'], $_POST);
                $audit->log('job_updated', 'Jobs', 'La empresa actualizó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::redirect('./?action=company_jobs', 'Vacante actualizada correctamente.');
            } elseif ($action === 'close_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $jobsController->close($jobId, $user['company_id']);
                $audit->log('job_closed', 'Jobs', 'La empresa cerró una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                self::redirect('./?action=company_jobs', 'Vacante cerrada.');
            } elseif ($action === 'delete_cv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $userController->deleteCv($user['id']);
                $audit->log('cv_deleted', 'Users', 'El candidato eliminó su hoja de vida.', 'success', $user['id']);
                self::redirect('./?action=profile', 'Hoja de vida eliminada.');
            } elseif ($action === 'mark_notification_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$user) throw new \RuntimeException('Acceso no autorizado.');
                $notificationsController->markAsRead((int) ($_POST['notification_id'] ?? 0), $user['id']);
                self::redirect('./?action=alerts', 'Notificación marcada como leída.');
            } elseif ($action === 'mark_all_notifications_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$user) throw new \RuntimeException('Acceso no autorizado.');
                $notificationsController->markAllAsRead($user['id']);
                self::redirect('./?action=alerts', 'Todas las notificaciones marcadas como leídas.');
            } elseif ($action === 'admin_delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'admin');
                $targetId = (int) ($_POST['user_id'] ?? 0);
                $dashboardController->deleteUser($user['id'], $targetId);
                self::redirect('./?action=dashboard&tab=users', 'Usuario eliminado correctamente.');
            } elseif ($action === 'admin_suspend_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'admin');
                $targetId = (int) ($_POST['user_id'] ?? 0);
                $dashboardController->suspendUser($user['id'], $targetId);
                self::redirect('./?action=dashboard&tab=users', 'Usuario suspendido correctamente.');
            } elseif ($action === 'admin_activate_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'admin');
                $targetId = (int) ($_POST['user_id'] ?? 0);
                $dashboardController->activateUser($user['id'], $targetId);
                self::redirect('./?action=dashboard&tab=users', 'Usuario activado correctamente.');
            } elseif ($action === 'admin_delete_company' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'admin');
                $companyId = (int) ($_POST['company_id'] ?? 0);
                $dashboardController->deleteCompany($user['id'], $companyId);
                self::redirect('./?action=dashboard&tab=companies', 'Empresa eliminada correctamente.');
            } elseif ($action === 'admin_delete_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'admin');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $dashboardController->deleteJob($user['id'], $jobId);
                self::redirect('./?action=dashboard&tab=jobs', 'Vacante eliminada correctamente.');
            } elseif ($action === 'admin_delete_application' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'admin');
                $applicationId = (int) ($_POST['application_id'] ?? 0);
                $dashboardController->deleteApplication($user['id'], $applicationId);
                self::redirect('./?action=dashboard&tab=applications', 'Postulación eliminada correctamente.');
            } elseif ($action === 'admin_create_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'admin');
                $dashboardController->createAdmin($user['id'], $_POST);
                self::redirect('./?action=dashboard&tab=create_admin', 'Usuario administrador creado exitosamente.');
            } elseif ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$user) {
                    throw new \RuntimeException('Debes iniciar sesión para enviar mensajes.');
                }
                $applicationId = (int) ($_POST['application_id'] ?? 0);
                $chatController->send($applicationId, $user, (string) ($_POST['message'] ?? ''));
                self::redirect('./?action=chat&application_id=' . $applicationId, 'Mensaje enviado.');
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            if ($user) {
                $audit->log('action_error', 'System', 'Acción rechazada: ' . substr($exception->getMessage(), 0, 180), 'error', $user['id'], ['action' => $action]);
            }
        }

        // Conserva los datos capturados en los formularios de registro cuando la validación falla.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['register', 'register_company'], true)) {
            $_SESSION['old_input'] = [
                'name' => (string) ($_POST['name'] ?? ''),
                'email' => (string) ($_POST['email'] ?? ''),
                'cedula' => (string) ($_POST['cedula'] ?? ''),
                'birth_date' => (string) ($_POST['birth_date'] ?? ''),
                'company_name' => (string) ($_POST['company_name'] ?? ''),
                'city' => (string) ($_POST['city'] ?? ''),
                // La lectura del documento se conserva para no obligar a repetirla
                // cuando el único problema es otro dato del formulario.
                'id_scan' => (string) ($_POST['id_scan'] ?? ''),
            ];
        }

        $jobs = [];

        if (isset($candidateProfile)) {
            $content = PortalViewRenderer::candidatePublicProfile($candidateProfile, $user, $message, $error);
        } elseif (isset($companyProfile)) {
            $content = PortalViewRenderer::companyPublicProfile($companyProfile, $user, $message, $error);
        } elseif (in_array($action, ['candidate_public_profile', 'company_public_profile'], true)) {
            $content = PortalViewRenderer::accessDenied($error, $user);
        } elseif ($user && $action === 'chat') {
            $applicationId = (int) ($_GET['application_id'] ?? 0);
            $chatData = $chatController->chat($applicationId, $user);
            $content = PortalViewRenderer::chatView($chatData['application'], $chatData['messages'], $chatData['can_send'], $user, $message, $error);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'dashboard') {
            $jobs = $searchController->jobs($_GET['q'] ?? '', $_GET['location'] ?? '');
            $content = PortalViewRenderer::candidateArea($user, $message, $error, $jobs, $userController, $applicationsController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'profile') {
            $content = PortalViewRenderer::candidateProfile($user, $message, $error, $userController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'applications') {
            $content = PortalViewRenderer::candidateApplications($user, $message, $error, $applicationsController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'messages') {
            $conversations = $chatController->conversations($user);
            $content = PortalViewRenderer::candidateMessages($user, $conversations, $message, $error);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'favorites') {
            $content = PortalViewRenderer::candidateFavorites($user, $message, $error, $searchController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'alerts') {
            $content = PortalViewRenderer::candidateAlerts($user, $message, $error, $notificationsController);
        } elseif ($user && $user['role'] === 'candidate' && $action === 'search') {
            $jobs = $searchController->jobs($_GET['q'] ?? '', $_GET['location'] ?? '');
            $content = PortalViewRenderer::candidateDashboard($user, $message, $error, $jobs, $searchController);
        } elseif ($user && $user['role'] === 'recruiter' && $action === 'alerts') {
            $content = PortalViewRenderer::companyAlerts($user, $message, $error, $notificationsController);
        } elseif ($user && $user['role'] === 'recruiter' && in_array($action, ['dashboard', 'company_profile', 'company_jobs', 'company_applications', 'company_messages'], true)) {
            $content = PortalViewRenderer::companyArea($user, $action, $message, $error, $companies, $jobsController, $applicationsController, $chatController);
        } elseif ($action === 'job_detail') {
            $jobDetail = $jobsController->find((int) ($_GET['id'] ?? 0));
            $content = PortalViewRenderer::jobDetail($jobDetail, $user, $message, $error);
        } else {
            $jobs = $searchController->jobs($_GET['q'] ?? '', $_GET['location'] ?? '');
            $content = ($action === 'dashboard' && $user)
                ? PortalViewRenderer::dashboard($user, $message, $error, $jobs, $dashboardController, $searchController)
                : PortalViewRenderer::home($jobs, $user, $action, $message, $error, $idScanConfig);
        }

        unset($_SESSION['old_input']);

        $notificationCount = $user ? $notificationsController->unreadCount($user['id']) : 0;
        $chatUnreadCount = $user ? $chatController->unreadCount($user) : 0;
        echo PortalViewRenderer::layout($content, $user, $notificationCount, $chatUnreadCount);
    }

    private static function role(?array $user, string $role): void
    {
        if (!$user || $user['role'] !== $role || ($role === 'recruiter' && empty($user['company_id']))) {
            throw new \RuntimeException('No tienes permisos para realizar esta acción.');
        }
    }

    /**
     * Envía al usuario a otra vista del portal con un aviso opcional.
     */
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
