<?php

namespace App\Http;

use Modules\Auth\Controllers\AuthController;
use Modules\Auth\Repositories\AuthRepository;
use Modules\Auth\Services\AuthService;
use Modules\Auditoria\Services\AuditService;
use Modules\Jobs\Controllers\JobsController;
use Modules\Jobs\Repositories\JobRepository;
use Modules\Jobs\Repositories\JobManagementRepository;
use Modules\Jobs\Services\JobManagementService;
use Modules\Applications\Controllers\ApplicationsController;
use Modules\Applications\Repositories\ApplicationRepository;
use Modules\Applications\Services\ApplicationService;
use Modules\Companies\Controllers\CompaniesController;
use Modules\Companies\Repositories\CompanyRepository;
use Modules\Companies\Services\CompanyFileService;
use Modules\Companies\Services\CompanyProfileService;
use Modules\Search\Repositories\JobInteractionRepository;
use Modules\Search\Controllers\SearchController;
use Modules\Search\Services\JobInteractionService;
use Modules\Search\Services\SearchService;
use Modules\Users\Repositories\UserRepository;
use Modules\Users\Controllers\UserController;
use Modules\Users\Services\UserFileService;
use Modules\Users\Services\UserProfileService;
use Modules\Dashboard\Controllers\DashboardController;
use Modules\Dashboard\Repositories\DashboardRepository;
use Modules\Dashboard\Services\DashboardService;
use Modules\Notifications\Controllers\NotificationsController;
use Modules\Notifications\Repositories\NotificationRepository;
use Modules\Notifications\Services\NotificationService;
use Throwable;

final class Portal
{
    public static function run(): void
    {
        $action = $_GET['action'] ?? 'home';
        $user = $_SESSION['user'] ?? null;
        $error = null;
        $message = null;
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
        $searchController = new SearchController(
            new SearchService(new JobRepository()),
            $jobInteractions
        );
        $notificationsController = new NotificationsController(
            new NotificationService(new NotificationRepository())
        );
        $dashboardController = new DashboardController(
            new DashboardService(new DashboardRepository())
        );
        $auth = new AuthController(new AuthService(new AuthRepository(), $audit));
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
                throw new \RuntimeException('La sesión del formulario expiró. Recarga la página.');
            }
            if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$auth->login($_POST['email'] ?? '', $_POST['password'] ?? '')) $error = 'Correo o contraseña incorrectos.';
                else { header('Location: ./?action=dashboard'); exit; }
            } elseif ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $auth->register($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
                $message = 'Cuenta creada. Ya puedes ingresar.';
                $action = 'login';
            } elseif ($action === 'upload_cv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $userController->uploadCv($user['id'], $_FILES['cv'] ?? []);
                $message = 'Hoja de vida guardada correctamente.';
                $action = 'dashboard';
            } elseif ($action === 'save_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$user || !in_array($user['role'], ['candidate', 'recruiter'], true)) throw new \RuntimeException('No tienes permisos para editar este perfil.');
                if ($user['role'] === 'candidate') {
                    $fields = ['name', 'phone', 'location', 'professional_title', 'bio', 'skills', 'experience', 'education', 'linkedin_url', 'portfolio_url', 'availability'];
                    $values = [];
                    foreach ($fields as $field) $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    $photo = ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
                        ? $_FILES['profile_photo']
                        : null;
                    $userController->updateCandidateProfile($user['id'], $values, $photo);
                } else {
                    $fields = ['name', 'description', 'city', 'industry', 'website', 'phone', 'contact_email', 'size'];
                    $values = [];
                    foreach ($fields as $field) $values[$field] = trim((string) ($_POST[$field] ?? '')) ?: null;
                    $photo = ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
                        ? $_FILES['profile_photo']
                        : null;
                    $companiesController->update($user['company_id'], $user['id'], $values, $photo);
                }
                $message = 'Perfil actualizado correctamente.';
                $action = 'dashboard';
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
            } elseif ($action === 'logout') {
                $auth->logout($user);
                header('Location: ./');
                exit;
            } elseif ($action === 'create_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                foreach (['title', 'description', 'city', 'experience', 'salary_min', 'salary_max'] as $field) if (trim((string) ($_POST[$field] ?? '')) === '') throw new \InvalidArgumentException('Completa todos los campos de la vacante.');
                $jobId = $jobsController->create($user['company_id'], $_POST);
                $audit->log('job_created', 'Jobs', 'La empresa creó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                $message = 'Vacante creada correctamente.'; $action = 'dashboard';
            } elseif ($action === 'apply' && $user && $user['role'] === 'candidate') {
                $applicationsController->apply((int) $_GET['job'], $user['id']);
                $audit->log('application_created', 'Applications', 'El candidato envió una postulación.', 'success', $user['id'], ['job_id' => (int) $_GET['job']]);
                $message = 'Postulación enviada correctamente.'; $action = 'dashboard';
            } elseif ($action === 'toggle_saved_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $searchController->toggleSaved($user['id'], $jobId);
                $audit->log('job_saved_toggled', 'Search', 'El candidato actualizó una vacante guardada.', 'success', $user['id'], ['job_id' => $jobId]);
                $action = 'dashboard';
            } elseif ($action === 'hide_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'candidate');
                $jobId = (int) ($_POST['job_id'] ?? 0);
                $searchController->hide($user['id'], $jobId);
                $audit->log('job_hidden', 'Search', 'El candidato ocultó una vacante.', 'success', $user['id'], ['job_id' => $jobId]);
                $action = 'dashboard';
            } elseif ($action === 'update_application' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                $applicationsController->updateStatus((int) $_POST['application_id'], $user['company_id'], $_POST['status']);
                $audit->log('application_status_updated', 'Applications', 'La empresa actualizó una postulación.', 'success', $user['id'], ['application_id' => (int) $_POST['application_id'], 'status' => $_POST['status']]);
                $message = 'Estado actualizado.'; $action = 'dashboard';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            if ($user) $audit->log('action_error', 'System', 'Acción rechazada: ' . substr($exception->getMessage(), 0, 180), 'error', $user['id'], ['action' => $action]);
        }

        $jobs = $searchController->jobs($_GET['q'] ?? '', $_GET['location'] ?? '');
        if ($user && $user['role'] === 'candidate' && $action === 'dashboard') $content = self::candidateArea($user, $message, $error, $jobs, $userController, $applicationsController);
        elseif ($user && $user['role'] === 'candidate' && $action === 'profile') $content = self::candidateProfile($user, $message, $error, $userController);
        elseif ($user && $user['role'] === 'candidate' && $action === 'applications') $content = self::candidateApplications($user, $message, $error, $applicationsController);
        elseif ($user && $user['role'] === 'candidate' && $action === 'favorites') $content = self::candidateFavorites($user, $message, $error, $searchController);
        elseif ($user && $user['role'] === 'candidate' && $action === 'alerts') $content = self::candidateAlerts($user, $message, $error, $notificationsController);
        elseif ($user && $user['role'] === 'candidate' && $action === 'search') $content = self::candidateDashboard($user, $message, $error, $jobs, $searchController);
        elseif ($user && $user['role'] === 'recruiter' && in_array($action, ['dashboard', 'company_profile', 'company_jobs', 'company_applications'], true)) $content = self::companyArea($user, $action, $message, $error, $companies, $jobsController, $applicationsController);
        else $content = $action === 'dashboard' && $user
            ? self::dashboard($user, $message, $error, $jobs, $companies, $jobsController, $applicationsController, $dashboardController)
            : self::home($jobs, $user, $action, $message, $error);
        echo self::layout($content, $user);
    }

    private static function role(?array $user, string $role): void
    {
        if (!$user || $user['role'] !== $role || ($role === 'recruiter' && empty($user['company_id']))) throw new \RuntimeException('No tienes permisos para realizar esta acción.');
    }

    private static function dashboard(array $user, ?string $message, ?string $error, array $searchJobs, CompanyProfileService $companies, JobsController $jobsController, ApplicationsController $applicationsController, DashboardController $dashboardController): string
    {
        $html = self::alerts($message, $error);
        if ($user['role'] === 'admin') {
            $summary = $dashboardController->summary();
            $cards = '';
            foreach ($summary['counts'] as $label => $count) $cards .= '<div class="rounded-2xl border bg-white p-5"><p class="text-sm capitalize text-slate-500">' . self::e($label) . '</p><p class="mt-2 text-3xl font-bold">' . $count . '</p></div>';
            $logs = $summary['logs'];
            $rows = '';
            foreach ($logs as $log) $rows .= '<tr class="border-t"><td class="px-3 py-3 text-xs">' . self::e($log['created_at']) . '</td><td class="px-3 py-3">' . self::e($log['user_name'] ?? 'Sistema') . '</td><td class="px-3 py-3 font-bold">' . self::e($log['action']) . '</td><td class="px-3 py-3">' . self::e($log['module']) . '</td><td class="px-3 py-3">' . self::e($log['description']) . '</td><td class="px-3 py-3">' . self::e($log['ip_address']) . '</td></tr>';
            return $html . '<div class="mb-8"><p class="font-bold uppercase tracking-widest text-[#fca311]">Administración</p><h1 class="display text-4xl font-bold">Panel de auditoría</h1><p class="mt-2 text-slate-500">Logs de acciones, respuestas y actividad del sistema.</p></div><div class="mb-8 grid gap-4 md:grid-cols-4">' . $cards . '</div><div class="overflow-x-auto rounded-2xl border bg-white p-4"><h2 class="display mb-4 text-2xl font-bold">Logs recientes</h2><table class="w-full min-w-[850px] text-left text-sm"><thead><tr class="text-xs uppercase text-slate-500"><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Usuario</th><th class="px-3 py-2">Acción</th><th class="px-3 py-2">Módulo</th><th class="px-3 py-2">Descripción</th><th class="px-3 py-2">IP</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }
        if ($user['role'] === 'recruiter') {
            $company = $companies->find($user['company_id']);
            $jobs = $jobsController->byCompany($user['company_id']);
            $jobRows = ''; foreach ($jobs as $job) $jobRows .= '<tr class="border-t"><td class="px-3 py-3 font-bold">' . self::e($job['title']) . '</td><td class="px-3 py-3">' . self::e($job['status']) . '</td><td class="px-3 py-3">' . $job['applications_count'] . '</td></tr>';
            $applications = $applicationsController->byCompany($user['company_id']);
            $applicationRows = ''; foreach ($applications as $item) { $cvLink = $item['cv_original_name'] ? '<a class="font-bold text-blue-600" href="?action=download_cv&candidate=' . $item['candidate_id'] . '">Ver CV</a>' : '<span class="text-slate-400">Sin CV</span>'; $applicationRows .= '<tr class="border-t"><td class="px-3 py-3">' . self::e($item['candidate_name']) . '<br><span class="text-xs text-slate-500">' . self::e($item['email']) . '</span><br>' . $cvLink . '</td><td class="px-3 py-3">' . self::e($item['title']) . '</td><td class="px-3 py-3"><form method="post" action="?action=update_application" class="flex gap-2"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="application_id" value="' . $item['id'] . '"><select name="status" class="rounded border p-1"><option value="pending">Pendiente</option><option value="review">En revisión</option><option value="interview">Entrevista</option><option value="accepted">Aceptado</option><option value="rejected">Rechazado</option></select><button class="font-bold text-blue-600">Guardar</button></form></td></tr>'; }
            $companyPhotoStatus = $company['profile_photo_path'] ? '<div class="flex items-center gap-3"><img src="?action=download_profile_photo&type=company&id=' . $user['company_id'] . '" alt="Logo de empresa" class="h-16 w-16 rounded-lg object-cover"><p class="text-sm text-emerald-700">Logo o foto corporativa cargada.</p></div>' : '<p class="text-sm text-slate-500">No has cargado logo o foto corporativa.</p>';
            $companyForm = '<form method="post" action="?action=save_profile" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre de la empresa', $company['name']) . self::fieldValue('city', 'Ciudad', $company['city']) . self::fieldValue('industry', 'Sector', $company['industry']) . self::fieldValue('size', 'Tamaño de empresa', $company['size']) . self::fieldValue('website', 'Sitio web', $company['website'], 'url') . self::fieldValue('phone', 'Teléfono', $company['phone']) . self::fieldValue('contact_email', 'Correo de contacto', $company['contact_email'], 'email') . self::fieldValue('description', 'Descripción de la empresa', $company['description'], 'textarea') . '<label class="text-sm font-bold md:col-span-2">Logo o foto corporativa (JPG, PNG o WEBP, máximo 2 MB)<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-lg border p-3"></label><div class="md:col-span-2">' . $companyPhotoStatus . '</div><button class="rounded-lg bg-[#14213d] p-3 font-bold text-white md:col-span-2">Guardar perfil corporativo</button></form>';
            return $html . '<div class="mb-8"><p class="font-bold uppercase tracking-widest text-[#fca311]">Empresa</p><h1 class="display text-4xl font-bold">' . self::e($company['name']) . '</h1><p class="mt-2 text-slate-500">Edita la información corporativa, crea publicaciones y gestiona candidatos.</p></div><div class="mb-8 rounded-2xl border bg-white p-6"><h2 class="display mb-5 text-2xl font-bold">Perfil corporativo</h2>' . $companyForm . '</div><div class="grid gap-8 lg:grid-cols-[.8fr_1.2fr]"><form method="post" action="?action=create_job" class="rounded-2xl border bg-white p-6"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><h2 class="display mb-5 text-2xl font-bold">Crear vacante</h2>' . self::field('title', 'Título') . self::field('description', 'Descripción', 'textarea') . self::field('city', 'Ciudad') . '<div class="grid grid-cols-2 gap-3">' . self::field('experience', 'Experiencia') . self::field('salary_min', 'Salario mínimo', 'number') . '</div>' . self::field('salary_max', 'Salario máximo', 'number') . '<label class="mt-4 block text-sm font-bold">Modalidad<select name="work_mode" class="mt-1 w-full rounded-lg border p-3"><option value="hybrid">Híbrido</option><option value="remote">Remoto</option><option value="onsite">Presencial</option></select></label><label class="mt-4 block text-sm font-bold">Estado<select name="status" class="mt-1 w-full rounded-lg border p-3"><option value="published">Publicar ahora</option><option value="draft">Guardar borrador</option></select></label><button class="mt-5 w-full rounded-lg bg-[#14213d] p-3 font-bold text-white">Crear vacante</button></form><div class="space-y-8"><div class="rounded-2xl border bg-white p-6"><h2 class="display mb-4 text-2xl font-bold">Mis vacantes</h2><table class="w-full text-left text-sm"><thead><tr class="text-slate-500"><th class="px-3 py-2">Vacante</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Postulaciones</th></tr></thead><tbody>' . $jobRows . '</tbody></table></div><div class="rounded-2xl border bg-white p-6"><h2 class="display mb-4 text-2xl font-bold">Candidatos</h2><div class="overflow-x-auto"><table class="w-full min-w-[600px] text-left text-sm"><thead><tr class="text-slate-500"><th class="px-3 py-2">Candidato</th><th class="px-3 py-2">Vacante</th><th class="px-3 py-2">Estado</th></tr></thead><tbody>' . $applicationRows . '</tbody></table></div></div></div></div>';
        }
        return self::candidateDashboard($user, $message, $error, $searchJobs, new SearchController(new SearchService(new JobRepository()), new JobInteractionService(new JobInteractionRepository()))); /*
        $stmt = $db->prepare('SELECT applications.*,jobs.title,companies.name company_name FROM applications JOIN jobs ON jobs.id=applications.job_id JOIN companies ON companies.id=jobs.company_id WHERE applications.user_id=:user ORDER BY applications.created_at DESC'); $stmt->execute(['user' => $user['id']]); $rows = '';
        foreach ($stmt->fetchAll() as $item) $rows .= '<div class="flex items-center justify-between border-t py-4"><div><p class="font-bold">' . self::e($item['title']) . '</p><p class="text-sm text-slate-500">' . self::e($item['company_name']) . '</p></div><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">' . self::e($item['status']) . '</span></div>';
        $profile = $db->prepare('SELECT * FROM users WHERE id=:user'); $profile->execute(['user' => $user['id']]); $profile = $profile->fetch();
        $cv = $profile;
        $cvStatus = $cv['cv_original_name'] ? '<p class="text-sm text-emerald-700">Archivo actual: <strong>' . self::e($cv['cv_original_name']) . '</strong> (' . number_format($cv['cv_size'] / 1024, 0) . ' KB) · <a class="font-bold text-blue-600" href="?action=download_cv&candidate=' . $user['id'] . '">Descargar</a></p>' : '<p class="text-sm text-slate-500">Todavía no has cargado una hoja de vida.</p>';
        $photoStatus = $profile['profile_photo_path'] ? '<div class="flex items-center gap-3"><img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto de perfil" class="h-16 w-16 rounded-full object-cover"><p class="text-sm text-emerald-700">Foto de perfil cargada.</p></div>' : '<p class="text-sm text-slate-500">No has cargado foto de perfil.</p>';
        $profileForm = '<form method="post" action="?action=save_profile" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre completo', $profile['name']) . self::fieldValue('professional_title', 'Cargo o titular profesional', $profile['professional_title']) . self::fieldValue('phone', 'Teléfono', $profile['phone']) . self::fieldValue('location', 'Ciudad / ubicación', $profile['location']) . self::fieldValue('availability', 'Disponibilidad', $profile['availability']) . self::fieldValue('linkedin_url', 'LinkedIn', $profile['linkedin_url'], 'url') . self::fieldValue('portfolio_url', 'Portafolio', $profile['portfolio_url'], 'url') . self::fieldValue('skills', 'Habilidades', $profile['skills'], 'textarea') . self::fieldValue('experience', 'Experiencia laboral', $profile['experience'], 'textarea') . self::fieldValue('education', 'Educación', $profile['education'], 'textarea') . self::fieldValue('bio', 'Resumen profesional', $profile['bio'], 'textarea') . '<label class="text-sm font-bold md:col-span-2">Foto de perfil (JPG, PNG o WEBP, máximo 2 MB)<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-lg border p-3"></label><div class="md:col-span-2">' . $photoStatus . '</div><button class="rounded-lg bg-[#14213d] p-3 font-bold text-white md:col-span-2">Guardar perfil profesional</button></form>';
        return $html . '<div class="mb-8"><p class="font-bold uppercase tracking-widest text-blue-600">Dashboard</p><h1 class="display text-4xl font-bold">Tu actividad</h1></div><div class="mb-8 rounded-2xl border bg-white p-6"><h2 class="display mb-5 text-2xl font-bold">Perfil profesional</h2><p class="mb-5 text-sm text-slate-500">Completa la información que ayudará a las empresas a conocerte.</p>' . $profileForm . '</div><div class="mb-8 rounded-2xl border bg-white p-6"><h2 class="display mb-2 text-2xl font-bold">Hoja de vida</h2><p class="mb-4 text-sm text-slate-500">Sube un PDF, DOC o DOCX de máximo 5 MB. Las empresas solo podrán verlo cuando te postules a una de sus vacantes.</p>' . $cvStatus . '<form method="post" action="?action=upload_cv" enctype="multipart/form-data" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><label class="flex-1 text-sm font-bold">Seleccionar archivo<input required type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="mt-1 block w-full rounded-lg border p-3"></label><button class="rounded-lg bg-[#14213d] px-5 py-3 font-bold text-white">Guardar hoja de vida</button></form></div><div class="rounded-2xl border bg-white p-6"><h2 class="display mb-5 text-2xl font-bold">Historial de postulaciones</h2>' . ($rows ?: '<p class="text-slate-500">Aún no tienes postulaciones.</p>') . '</div>';
        */
    }

    private static function companyNav(string $active): string
    {
        $items = ['dashboard' => '▦ Resumen', 'company_profile' => '◉ Perfil de empresa', 'company_jobs' => '▤ Mis vacantes', 'company_applications' => '♙ Candidatos'];
        $html = '<nav class="profile-tabs company-tabs">';
        foreach ($items as $key => $label) $html .= '<a class="' . ($key === $active ? 'active' : '') . '" href="?action=' . $key . '">' . $label . '</a>';
        return $html . '</nav>';
    }

    private static function companyArea(array $user, string $action, ?string $message, ?string $error, CompanyProfileService $companies, JobsController $jobsController, ApplicationsController $applicationsController): string
    {
        $company = $companies->find($user['company_id']);
        $jobList = $jobsController->byCompany($user['company_id']);
        $applicationList = $applicationsController->byCompany($user['company_id']);
        $profilePhoto = $company['profile_photo_path'] ? '<img src="?action=download_profile_photo&type=company&id=' . $user['company_id'] . '" alt="Logo" class="company-logo">' : '<div class="company-logo company-logo-empty">' . strtoupper(substr($company['name'], 0, 1)) . '</div>';
        $content = self::alerts($message, $error) . '<section class="candidate-page company-page">' . self::companyNav($action) . '<div class="company-heading"><div>' . $profilePhoto . '</div><div><p class="eyebrow">Panel de empresa</p><h1>' . self::e($company['name']) . '</h1><p>' . self::e($company['industry'] ?: 'Empresa reclutadora') . ' · ' . self::e($company['city']) . '</p></div></div>';
            if ($action === 'company_profile') $content .= self::companyProfileView($company, $user);
        elseif ($action === 'company_jobs') $content .= self::companyJobsView($jobList, $user);
        elseif ($action === 'company_applications') $content .= self::companyApplicationsView($applicationList, $user);
        else {
            $published = count(array_filter($jobList, fn (array $job): bool => $job['status'] === 'published'));
            $pending = count(array_filter($applicationList, fn (array $item): bool => in_array($item['status'], ['pending', 'review'], true)));
            $cards = '<div class="company-metrics"><div><span>Vacantes publicadas</span><strong>' . $published . '</strong></div><div><span>Total de vacantes</span><strong>' . count($jobList) . '</strong></div><div><span>Candidatos pendientes</span><strong>' . $pending . '</strong></div><div><span>Postulaciones recibidas</span><strong>' . count($applicationList) . '</strong></div></div>';
            $recent = ''; foreach (array_slice($applicationList, 0, 5) as $item) $recent .= '<a class="application-card" href="?action=company_applications"><div><h2>' . self::e($item['candidate_name']) . '</h2><p>' . self::e($item['title']) . '</p></div><strong>' . self::e($item['status']) . '</strong></a>';
            $content .= $cards . '<div class="company-columns"><article class="profile-card"><div class="flex items-center justify-between"><h2>Actividad reciente</h2><a class="profile-link" href="?action=company_applications">Ver candidatos →</a></div>' . ($recent ?: '<p class="mt-5 text-slate-500">Aún no has recibido postulaciones.</p>') . '</article><article class="premium-card company-cta"><h2>Publica una nueva vacante</h2><p>Encuentra talento y gestiona todo el proceso desde un solo lugar.</p><a href="?action=company_jobs#crear">Crear vacante</a></article></div>';
        }
        return $content . '</section>';
    }

    private static function companyProfileView(array $company, array $user): string
    {
        return '<article class="profile-card company-section"><div class="flex items-center justify-between"><h2>Información corporativa</h2><span class="verified-badge">✓ Empresa verificada</span></div><p class="section-copy">Mantén actualizados los datos que verán los candidatos.</p><form method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre de empresa', $company['name']) . self::fieldValue('city', 'Ciudad', $company['city']) . self::fieldValue('industry', 'Sector', $company['industry']) . self::fieldValue('size', 'Tamaño', $company['size']) . self::fieldValue('website', 'Sitio web', $company['website'], 'url') . self::fieldValue('phone', 'Teléfono', $company['phone']) . self::fieldValue('contact_email', 'Correo de contacto', $company['contact_email'], 'email') . self::fieldValue('description', 'Descripción', $company['description'], 'textarea') . '<label>Logo o foto corporativa<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><button class="primary-button">Guardar perfil corporativo</button></form></article>';
    }

    private static function companyJobsView(array $jobs, array $user): string
    {
        $rows = ''; foreach ($jobs as $job) $rows .= '<article class="application-card"><div><h2>' . self::e($job['title']) . '</h2><p>' . self::e($job['city']) . ' · ' . self::e($job['work_mode']) . '</p></div><div class="text-right"><strong>' . self::e($job['status']) . '</strong><p class="text-sm text-slate-500">' . $job['applications_count'] . ' candidatos</p></div></article>';
        return '<div class="company-columns" id="crear"><form method="post" action="?action=create_job" class="profile-card company-section"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><h2>Crear una vacante</h2><p class="section-copy">Publica oportunidades claras y atractivas para los candidatos.</p>' . self::field('title', 'Título') . self::field('description', 'Descripción', 'textarea') . self::field('city', 'Ciudad') . self::field('experience', 'Experiencia') . '<div class="grid grid-cols-2 gap-3">' . self::field('salary_min', 'Salario mínimo', 'number') . self::field('salary_max', 'Salario máximo', 'number') . '</div><label class="mt-4 block text-sm font-bold">Modalidad<select name="work_mode" class="mt-1 w-full rounded-lg border p-3"><option value="hybrid">Híbrido</option><option value="remote">Remoto</option><option value="onsite">Presencial</option></select></label><button class="primary-button mt-5">Publicar vacante</button></form><article class="profile-card company-section"><h2>Mis vacantes</h2><div class="mt-5">' . ($rows ?: '<p class="text-slate-500">Aún no tienes vacantes.</p>') . '</div></article></div>';
    }

    private static function companyApplicationsView(array $applications, array $user): string
    {
        $rows = ''; foreach ($applications as $item) { $cv = $item['cv_original_name'] ? '<a class="profile-link" href="?action=download_cv&candidate=' . $item['candidate_id'] . '">Ver hoja de vida</a>' : '<span class="text-slate-400">Sin CV</span>'; $rows .= '<article class="application-card"><div><h2>' . self::e($item['candidate_name']) . '</h2><p>' . self::e($item['email']) . ' · ' . self::e($item['title']) . '</p>' . $cv . '</div><form method="post" action="?action=update_application" class="flex items-center gap-2"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="application_id" value="' . $item['id'] . '"><select name="status" class="rounded-lg border p-2"><option value="pending">Pendiente</option><option value="review">En revisión</option><option value="interview">Entrevista</option><option value="accepted">Aceptado</option><option value="rejected">Rechazado</option></select><button class="primary-button">Actualizar</button></form></article>'; }
        return '<article class="profile-card company-section"><div class="flex items-center justify-between"><div><h2>Candidatos</h2><p class="section-copy">Revisa perfiles, descarga hojas de vida y actualiza cada proceso.</p></div><span class="company-count">' . count($applications) . ' postulaciones</span></div><div class="mt-5">' . ($rows ?: '<p class="text-slate-500">No hay postulaciones recibidas.</p>') . '</div></article>';
    }

    private static function candidateDashboard(array $user, ?string $message, ?string $error, array $searchJobs, SearchController $searchController): string
    {
        $html = self::alerts($message, $error);
        $keyword = self::e($_GET['q'] ?? '');
        $location = self::e($_GET['location'] ?? '');
        $selectedId = (int) ($_GET['job'] ?? ($searchJobs[0]['id'] ?? 0));
        $hiddenIds = $searchController->hiddenJobIds($user['id']);
        $savedIds = $searchController->savedJobIds($user['id']);
        $jobs = array_values(array_filter($searchJobs, fn (array $job): bool => !in_array((int) $job['id'], $hiddenIds, true)));
        if (!$jobs) $selectedId = 0;
        $selected = null;
        foreach ($jobs as $job) if ((int) $job['id'] === $selectedId) $selected = $job;
        $selected ??= $jobs[0] ?? null;
        $jobCards = '';
        foreach ($jobs as $job) {
            $isSelected = $selected && (int) $selected['id'] === (int) $job['id'];
            $isSaved = in_array((int) $job['id'], $savedIds, true);
            $jobCards .= '<a href="?action=dashboard&q=' . rawurlencode($_GET['q'] ?? '') . '&location=' . rawurlencode($_GET['location'] ?? '') . '&job=' . $job['id'] . '" class="block border-b border-slate-200 p-5 transition ' . ($isSelected ? 'border-l-4 border-l-[#17457c] bg-white shadow-sm' : 'bg-white hover:bg-slate-50') . '"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-[#c64a44]">Nueva oportunidad</p><h3 class="mt-2 text-lg font-bold text-[#25364b]">' . self::e($job['title']) . '</h3><p class="mt-1 text-sm font-medium text-slate-600">' . self::e($job['company_name']) . '</p></div><span class="text-lg ' . ($isSaved ? 'text-[#17457c]' : 'text-slate-300') . '">' . ($isSaved ? '♥' : '♡') . '</span></div><p class="mt-3 text-sm text-slate-600">' . self::e($job['city']) . ' · ' . self::e($job['work_mode']) . '</p><div class="mt-4 flex items-center justify-between text-xs text-slate-500"><span>' . self::e($job['experience']) . '</span><span>' . date('d M', strtotime($job['created_at'])) . '</span></div></a>';
        }
        $detail = $selected ? '<div class="flex items-start justify-between gap-5"><div><p class="text-sm font-bold uppercase tracking-wide text-[#c64a44]">Oportunidad seleccionada</p><h1 class="mt-3 text-3xl font-bold text-[#25364b]">' . self::e($selected['title']) . '</h1><p class="mt-2 text-lg font-semibold text-[#25364b]">' . self::e($selected['company_name']) . '</p><p class="mt-2 text-slate-600">' . self::e($selected['city']) . '</p></div><div class="hidden h-16 w-16 items-center justify-center rounded bg-slate-100 text-2xl text-slate-400 sm:flex">▦</div></div><div class="mt-7 flex flex-wrap gap-3"><a href="?action=apply&job=' . $selected['id'] . '" class="rounded-full bg-[#17457c] px-8 py-3 font-bold text-white shadow-sm transition hover:bg-[#12365f]">Aplicar</a><form method="post" action="?action=toggle_saved_job"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="' . $selected['id'] . '"><button class="circle-action" title="Guardar vacante">' . (in_array((int) $selected['id'], $savedIds, true) ? '♥' : '♡') . '</button></form><button class="circle-action" title="Compartir vacante" onclick="navigator.clipboard?.writeText(window.location.href);this.textContent=' . "'✓'" . ';return false">↗</button><form method="post" action="?action=hide_job"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="' . $selected['id'] . '"><button class="circle-action" title="Ocultar vacante">◉</button></form><button class="circle-action" title="Más opciones">⋮</button></div><div class="my-7 border-t border-slate-200"></div><div class="grid gap-4 text-sm text-slate-700 sm:grid-cols-3"><div><span class="text-xl">▣</span><p class="mt-2 font-semibold">' . self::e($selected['work_mode']) . '</p><span class="text-slate-500">Modalidad</span></div><div><span class="text-xl">◷</span><p class="mt-2 font-semibold">' . self::e($selected['experience']) . '</p><span class="text-slate-500">Experiencia</span></div><div><span class="text-xl">$</span><p class="mt-2 font-semibold">$' . number_format((float) $selected['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $selected['salary_max'], 0, ',', '.') . '</p><span class="text-slate-500">Salario</span></div></div><div class="mt-8 border-t border-slate-200 pt-7"><h2 class="text-xl font-bold text-[#25364b]">Descripción de la oferta</h2><p class="mt-4 whitespace-pre-line leading-7 text-slate-700">' . self::e($selected['description']) . '</p></div>' : '<div class="flex h-full items-center justify-center p-12 text-center text-slate-500">No hay vacantes con estos filtros.</div>';
        return $html . '<section class="dashboard-shell -mx-5 min-h-[calc(100vh-78px)] bg-[#eef5fa] md:-mx-8"><div class="border-b border-slate-200 bg-[#f5fbff] px-5 py-5 md:px-8"><form method="get" class="mx-auto flex max-w-7xl flex-col gap-3 md:flex-row"><input type="hidden" name="action" value="dashboard"><div class="search-field flex-1"><span>▣</span><input name="q" value="' . $keyword . '" placeholder="Cargo o categoría"></div><div class="search-field flex-1"><span>⌖</span><input name="location" value="' . $location . '" placeholder="Lugar"></div><button class="rounded-full bg-[#17457c] px-7 py-3 font-bold text-white">⌕ Buscar empleos</button></form><div class="mx-auto mt-4 flex max-w-7xl gap-2 overflow-x-auto pb-1"><a href="?action=dashboard" class="filter-pill">Ordenar: recientes⌄</a><a href="?action=dashboard&location=Remote" class="filter-pill">Modalidad⌄</a><a href="?action=dashboard&q=junior" class="filter-pill">Experiencia⌄</a><a href="?action=dashboard&q=developer" class="filter-pill">Categoría⌄</a><a href="?action=dashboard" class="filter-pill">Salario⌄</a><a href="?action=dashboard" class="filter-pill">Jornada⌄</a></div></div><div class="mx-auto grid max-w-7xl lg:grid-cols-[minmax(330px,39%)_1fr]"><aside class="max-h-[calc(100vh-210px)] overflow-y-auto border-r border-slate-200"><div class="border-b border-slate-200 bg-white px-5 py-5"><p class="text-xl font-bold text-[#25364b]"><strong>' . count($jobs) . '</strong> ofertas disponibles</p><p class="mt-1 text-sm text-slate-500">Selecciona una oferta para ver sus detalles.</p></div>' . $jobCards . '</aside><article class="min-h-[calc(100vh-210px)] bg-white p-6 md:p-10">' . $detail . '</article></div></section>';
    }

    private static function candidateNav(string $active): string
    {
        $items = ['dashboard' => '⌂ Mi área', 'profile' => '▣ Hoja de Vida', 'applications' => '➤ Aplicaciones', 'alerts' => '♧ Mis alertas', 'favorites' => '♡ Mis favoritos'];
        $html = '<nav class="profile-tabs">';
        foreach ($items as $key => $label) $html .= '<a class="' . ($key === $active ? 'active' : '') . '" href="?action=' . $key . '">' . $label . '</a>';
        return $html . '</nav>';
    }

    private static function candidateArea(array $user, ?string $message, ?string $error, array $jobs, UserController $userController, ApplicationsController $applicationsController): string
    {
        $profile = $userController->profile($user['id']);
        $counts = array_fill_keys(['pending', 'review', 'interview', 'accepted', 'rejected'], 0);
        foreach ($applicationsController->countByStatus($user['id']) as $row) $counts[$row['status']] = (int) $row['total'];
        $completed = 30 + ($profile['cv_path'] ? 25 : 0) + ($profile['professional_title'] ? 15 : 0) + ($profile['skills'] ? 15 : 0) + ($profile['education'] ? 15 : 0);
        $recommendations = '';
        foreach (array_slice($jobs, 0, 4) as $job) $recommendations .= '<a href="?action=search&job=' . $job['id'] . '" class="profile-job"><p class="text-xs font-bold uppercase text-[#c64a44]">Oportunidad recomendada</p><h3>' . self::e($job['title']) . '</h3><p>' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p><span>$' . number_format((float) $job['salary_min'], 0, ',', '.') . '</span></a>';
        $photo = $profile['profile_photo_path'] ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto de perfil" class="profile-avatar">' : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('dashboard') . '<div class="profile-grid"><div><article class="profile-card"><div class="flex items-center gap-5">' . $photo . '<div><h1>' . self::e($profile['name']) . '</h1><p>' . self::e($profile['professional_title'] ?: 'Completa tu titular profesional') . '</p><p class="profile-completion">Perfil completado <strong>' . $completed . '%</strong></p></div><a class="ml-auto text-2xl" href="?action=profile">›</a></div><div class="progress-track"><span style="width:' . $completed . '%"></span></div><div class="mt-6 flex justify-between text-sm text-slate-500"><span>Tu perfil profesional</span><a class="font-bold text-[#17457c]" href="?action=profile">Editar perfil</a></div></article><article class="profile-card mt-6"><h2>Mis aplicaciones</h2><div class="stats-grid"><div><b>✓</b><span>Postulado</span><strong>' . ($counts['pending'] + $counts['review']) . '</strong></div><div><b>◉</b><span>En proceso</span><strong>' . $counts['interview'] . '</strong></div><div><b>★</b><span>Finalista</span><strong>' . $counts['accepted'] . '</strong></div><div><b>×</b><span>Finalizadas</span><strong>' . $counts['rejected'] . '</strong></div></div><a href="?action=applications" class="profile-link">Ver mis aplicaciones →</a></article><article class="profile-card mt-6"><h2>Accesos rápidos</h2><div class="quick-links"><a href="?action=profile">▣ <span>Editar hoja de vida</span> ›</a><a href="?action=favorites">♡ <span>Ofertas favoritas</span> ›</a><a href="?action=alerts">♧ <span>Configurar alertas</span> ›</a><a href="?action=search">⌕ <span>Buscar empleos</span> ›</a></div></article></div><div><article class="profile-card recommendations"><h2>Descubre estas <strong>ofertas que te pueden interesar</strong></h2>' . ($recommendations ?: '<p class="p-6 text-slate-500">Aún no hay recomendaciones disponibles.</p>') . '</article><article class="premium-card mt-6"><h2>Potencia tu búsqueda de empleo</h2><p>Completa tu perfil y mantén tu hoja de vida actualizada para destacar ante las empresas.</p><a href="?action=profile">Completar perfil</a></article></div></div></section>';
    }

    private static function candidateProfile(array $user, ?string $message, ?string $error, UserController $userController): string
    {
        $profile = $userController->profile($user['id']);
        $cv = $profile['cv_original_name'] ? '<p class="text-emerald-700">' . self::e($profile['cv_original_name']) . ' · <a class="font-bold" href="?action=download_cv&candidate=' . $user['id'] . '">Descargar</a></p>' : '<p class="text-slate-500">No has cargado una hoja de vida.</p>';
        $photo = $profile['profile_photo_path'] ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto" class="profile-avatar">' : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';
        $form = '<form method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre completo', $profile['name']) . self::fieldValue('professional_title', 'Titular profesional', $profile['professional_title']) . self::fieldValue('phone', 'Teléfono', $profile['phone']) . self::fieldValue('location', 'Ubicación', $profile['location']) . self::fieldValue('availability', 'Disponibilidad', $profile['availability']) . self::fieldValue('linkedin_url', 'LinkedIn', $profile['linkedin_url'], 'url') . self::fieldValue('portfolio_url', 'Portafolio', $profile['portfolio_url'], 'url') . self::fieldValue('skills', 'Conocimientos y habilidades', $profile['skills'], 'textarea') . self::fieldValue('experience', 'Experiencia profesional', $profile['experience'], 'textarea') . self::fieldValue('education', 'Educación', $profile['education'], 'textarea') . self::fieldValue('bio', 'Resumen profesional', $profile['bio'], 'textarea') . '<label>Foto de perfil<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><button class="primary-button">Guardar cambios</button></form>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('profile') . '<h1 class="page-title">Hoja de Vida</h1><div class="profile-layout"><div><article class="profile-card profile-identity"><div class="flex items-center gap-5">' . $photo . '<div><h2>' . self::e($profile['name']) . '</h2><p>' . self::e($profile['location'] ?: 'Agrega tu ubicación') . '</p><p>' . self::e($profile['phone'] ?: 'Agrega tu teléfono') . '</p></div></div><div class="mt-8">' . $form . '</div></article><article class="profile-card mt-6"><h2>Documentos adjuntos</h2><div class="mt-4">' . $cv . '</div><form method="post" action="?action=upload_cv" enctype="multipart/form-data" class="mt-5 flex gap-3"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required type="file" name="cv" accept=".pdf,.doc,.docx" class="flex-1 rounded-lg border p-3"><button class="primary-button">Subir o modificar CV</button></form></article></div><aside class="profile-card side-card"><h2>Tu perfil profesional</h2><p>Una información completa ayuda a las empresas a conocerte mejor.</p><a href="?action=dashboard">Volver a Mi área</a></aside></div></section>';
    }

    private static function candidateApplications(array $user, ?string $message, ?string $error, ApplicationsController $applicationsController): string
    {
        $rows = '';
        foreach ($applicationsController->byUser($user['id']) as $item) $rows .= '<article class="application-card"><div><h2>' . self::e($item['title']) . '</h2><p>' . self::e($item['company_name']) . ' · ' . self::e($item['city']) . '</p></div><strong>' . self::e($item['status']) . '</strong></article>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('applications') . '<h1 class="page-title">Mis aplicaciones</h1><div class="application-list">' . ($rows ?: '<div class="profile-card p-8 text-slate-500">Aún no tienes aplicaciones.</div>') . '</div></section>';
    }

    private static function candidateFavorites(array $user, ?string $message, ?string $error, SearchController $searchController): string
    {
        $rows = '';
        foreach ($searchController->savedJobs($user['id']) as $job) $rows .= '<a class="application-card" href="?action=search&job=' . $job['id'] . '"><div><h2>' . self::e($job['title']) . '</h2><p>' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p></div><span class="text-xl text-pink-500">♥</span></a>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('favorites') . '<h1 class="page-title">Mis favoritos</h1><div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><p class="text-5xl">♡</p><h2>Todavía no tienes ofertas guardadas</h2><a href="?action=search" class="primary-button">Buscar empleos</a></div>') . '</div></section>';
    }

    private static function candidateAlerts(array $user, ?string $message, ?string $error, NotificationsController $notificationsController): string
    {
        $rows = '';
        foreach ($notificationsController->forUser($user['id']) as $item) $rows .= '<article class="application-card"><div><h2>' . self::e($item['message']) . '</h2><p>' . self::e($item['created_at']) . '</p></div><span>♧</span></article>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('alerts') . '<h1 class="page-title">Mis alertas</h1><div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><p class="text-5xl">♧</p><h2>No tienes alertas todavía</h2><p>Pronto recibirás recomendaciones relacionadas con tu perfil.</p></div>') . '</div></section>';
    }

    private static function home(array $jobs, ?array $user, string $action, ?string $message, ?string $error): string
    {
        $alerts = self::alerts($message, $error);
        if ($action === 'login' || $action === 'register') return $alerts . '<section class="mx-auto max-w-md rounded-2xl border bg-white p-8 shadow-sm"><h1 class="display mb-6 text-4xl font-bold">' . ($action === 'login' ? 'Ingresar' : 'Crear perfil') . '</h1><form method="post" class="space-y-4"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . ($action === 'register' ? '<input required name="name" placeholder="Nombre completo" class="w-full rounded-lg border p-3"><input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3">' : '<input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3">') . '<input required name="password" type="password" placeholder="Contraseña" class="w-full rounded-lg border p-3"><button class="w-full rounded-lg bg-blue-600 p-3 font-bold text-white">Continuar</button></form></section>';
        $cards = '';
        foreach ($jobs as $job) $cards .= '<article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"><span class="rounded-full bg-[#eef3fa] px-3 py-1 text-xs font-bold text-[#17457c]">' . self::e($job['work_mode']) . '</span><h3 class="mt-5 text-xl font-bold">' . self::e($job['title']) . '</h3><p class="mt-2 text-sm font-medium text-slate-500">' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p><p class="mt-4 text-sm leading-6 text-slate-600">' . self::e($job['description']) . '</p><div class="mt-6 flex items-center justify-between border-t pt-4"><span class="text-sm font-bold">$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.') . '</span>' . ($user && $user['role'] === 'candidate' ? '<a href="?action=apply&job=' . $job['id'] . '" class="font-bold text-[#17457c]">Postularme →</a>' : '<a href="?action=login" class="font-bold text-[#17457c]">Ingresar →</a>') . '</div></article>';
        return $alerts . '<section class="landing-hero relative -mx-5 overflow-hidden px-5 py-16 text-white md:-mx-8 md:px-8 md:py-24"><div class="absolute inset-0 bg-[#102e50]/55"></div><div class="relative mx-auto max-w-6xl"><div class="max-w-3xl"><p class="mb-5 text-sm font-bold uppercase tracking-[.24em] text-[#fca311]">Youth Employment Bridge</p><h1 class="display text-5xl font-bold leading-[1.05] md:text-7xl">El próximo paso de tu carrera empieza aquí.</h1><p class="mt-6 max-w-2xl text-lg leading-8 text-white/90 md:text-xl">Encuentra oportunidades que encajen contigo y conecta con empresas que buscan tu talento.</p></div><form class="relative z-10 mt-10 grid gap-3 rounded-2xl bg-white p-3 shadow-2xl md:grid-cols-[1fr_1fr_auto]" method="get"><div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4"><span class="text-xl text-[#17457c]">⌕</span><input name="q" value="' . self::e($_GET['q'] ?? '') . '" placeholder="Cargo, habilidad o empresa" class="w-full py-3 text-[#14213d] outline-none"></div><div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4"><span class="text-xl text-[#17457c]">⌖</span><input name="location" value="' . self::e($_GET['location'] ?? '') . '" placeholder="Ciudad o modalidad" class="w-full py-3 text-[#14213d] outline-none"></div><button class="rounded-xl bg-[#17457c] px-7 py-3 font-bold text-white transition hover:bg-[#12365f]">Buscar empleos</button></form></div></section><section class="mx-auto max-w-6xl py-10"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-bold uppercase tracking-[.18em] text-[#17457c]">Explora oportunidades</p><h2 class="display mt-2 text-3xl font-bold">Vacantes para ti</h2></div><span class="text-sm text-slate-500">' . count($jobs) . ' resultados disponibles</span></div><div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">' . $cards . '</div></section><section class="border-t border-slate-200 py-10"><div class="grid gap-4 md:grid-cols-3"><a href="?action=register" class="rounded-2xl bg-[#14213d] p-6 text-white transition hover:-translate-y-1"><span class="text-2xl">✦</span><h3 class="mt-4 text-xl font-bold">Crea tu perfil</h3><p class="mt-2 text-sm text-white/70">Presenta tu experiencia y conecta con nuevas oportunidades.</p></a><a href="?action=login" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-lg"><span class="text-2xl text-[#fca311]">▣</span><h3 class="mt-4 text-xl font-bold">Sigue tus postulaciones</h3><p class="mt-2 text-sm text-slate-500">Consulta el estado de cada proceso desde tu dashboard.</p></a><a href="?action=register" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-lg"><span class="text-2xl text-[#17457c]">⌁</span><h3 class="mt-4 text-xl font-bold">Publica como empresa</h3><p class="mt-2 text-sm text-slate-500">Encuentra candidatos y gestiona tus vacantes.</p></a></div></section>';
    }

    private static function layout(string $content, ?array $user): string
    {
        $nav = $user ? '<a href="?action=dashboard" class="font-bold">' . self::e($user['name']) . '</a><a href="?action=logout" class="rounded-full border border-slate-200 px-4 py-2">Salir</a>' : '<a href="#vacantes" class="hidden text-sm font-medium md:inline">Buscar ofertas</a><a href="?action=login" class="text-sm font-medium">Ingresar</a><a href="?action=register" class="rounded-full bg-[#17457c] px-5 py-2.5 text-sm font-bold text-white">Crear perfil</a>';
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>YEB | Youth Employment Bridge</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"><style>body{font-family:DM Sans,sans-serif}.display{font-family:Fraunces,serif}.landing-hero{background-image:url("https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=2200&q=85");background-position:center;background-size:cover}.search-field{display:flex;align-items:center;gap:.75rem;border:1px solid #dbe4ec;border-radius:999px;background:#fff;padding:.25rem 1rem;color:#17457c}.search-field input{width:100%;background:transparent;padding:.65rem .25rem;outline:none}.filter-pill{white-space:nowrap;border:1px solid #dbe4ec;border-radius:.7rem;background:#fff;padding:.55rem 1rem;font-size:.85rem;font-weight:700;color:#40536a}.circle-action{display:inline-flex;height:3.25rem;width:3.25rem;align-items:center;justify-content:center;border-radius:999px;background:#edf5fc;color:#17457c;font-size:1.25rem;transition:background .2s}.circle-action:hover{background:#dbeaf7}.candidate-page{min-height:calc(100vh - 90px);background:#eef5fa;padding:1.5rem max(1rem,4vw) 4rem;margin:0 -2rem}.profile-tabs{display:flex;gap:.25rem;overflow:auto;border-radius:999px;background:#e5f0f9;padding:.3rem;margin-bottom:2rem}.profile-tabs a{white-space:nowrap;border-radius:999px;padding:.75rem 1.4rem;color:#587087;font-size:.9rem;font-weight:700}.profile-tabs a.active{background:#17457c;color:#fff;box-shadow:0 3px 10px #17457c30}.profile-grid{display:grid;grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr);gap:2rem;max-width:1180px;margin:auto}.profile-card,.premium-card{border-radius:1rem;background:#fff;padding:1.6rem;box-shadow:0 7px 20px #23415a0d}.profile-card h1{font-size:1.35rem;font-weight:700;color:#25364b}.profile-card h2{font-size:1.25rem;font-weight:700;color:#25364b}.profile-card p{color:#64788d}.profile-avatar{height:6.2rem;width:6.2rem;border-radius:999px;object-fit:cover;border:5px solid #7abf4d}.profile-avatar-empty{display:flex;align-items:center;justify-content:center;background:#14213d;color:#fff;font-size:2rem}.profile-completion{margin-top:.4rem}.profile-completion strong{color:#68a839}.progress-track{height:.5rem;border-radius:999px;background:#e9eef2;margin-top:1.4rem;overflow:hidden}.progress-track span{display:block;height:100%;border-radius:inherit;background:#7abf4d}.stats-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-top:1.2rem}.stats-grid div{display:grid;grid-template-columns:2.2rem 1fr auto;align-items:center;gap:.5rem;color:#52697e}.stats-grid b{display:grid;place-items:center;height:2.2rem;border-radius:50%;background:#edf5fc;color:#17457c}.stats-grid strong{color:#25364b}.profile-link{display:block;margin-top:1.3rem;color:#17457c;font-weight:700}.quick-links{display:grid;margin-top:1rem}.quick-links a{display:flex;align-items:center;gap:1rem;border-top:1px solid #edf0f3;padding:1rem 0;color:#17457c;font-size:1.05rem}.quick-links span{flex:1;color:#50677c;font-size:.95rem}.recommendations{padding:0;overflow:hidden}.recommendations h2{padding:1.5rem;border-bottom:1px solid #e7edf2;font-size:1.2rem;font-weight:400}.recommendations h2 strong{color:#17457c}.profile-job{display:block;border-bottom:1px solid #edf0f3;padding:1.25rem 1.5rem}.profile-job h3{margin-top:.4rem;font-size:1.05rem;font-weight:700;color:#25364b}.profile-job p{margin-top:.25rem;font-size:.9rem}.profile-job span{display:block;margin-top:.7rem;font-size:.9rem;font-weight:700;color:#17457c}.premium-card{background:#57b9aa;color:#fff;text-align:center}.premium-card h2{color:#fff}.premium-card p{margin:1rem 0;color:#effffb}.premium-card a,.primary-button{display:inline-block;border-radius:999px;background:#17457c;padding:.8rem 1.5rem;color:#fff;font-weight:700}.profile-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(250px,.5fr);gap:2rem;max-width:1180px;margin:auto}.page-title{max-width:1180px;margin:0 auto 1.2rem;font-size:2rem;color:#25364b}.profile-form{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-top:2rem}.profile-form label{display:block;color:#344d63;font-size:.9rem;font-weight:700}.profile-form label:nth-last-of-type(3),.profile-form label:nth-last-of-type(2),.profile-form label:nth-last-of-type(1){grid-column:1/-1}.profile-form input,.profile-form textarea{display:block;width:100%;margin-top:.35rem;border:1px solid #dbe4ec;border-radius:.6rem;padding:.75rem}.profile-form textarea{min-height:6rem}.profile-form .primary-button{border:0}.application-list{max-width:900px;margin:auto}.application-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;border-radius:1rem;background:#fff;padding:1.4rem 1.6rem;box-shadow:0 7px 20px #23415a0d}.application-card h2{font-size:1.05rem;font-weight:700;color:#25364b}.application-card p{margin-top:.3rem;color:#64788d}.application-card strong{color:#17457c}.empty-state{text-align:center;padding:4rem}.empty-state h2{margin:1rem 0 1.5rem;font-size:1.3rem;color:#25364b}.side-card{align-self:start}@media(max-width:1023px){.dashboard-shell aside{max-height:none}.dashboard-shell article{min-height:auto}.dashboard-shell{margin-bottom:2rem}.profile-grid,.profile-layout{grid-template-columns:1fr}.candidate-page{margin:0 -1.25rem}.profile-form{grid-template-columns:1fr}}</style></head><body class="bg-[#f8fafc] text-[#14213d]"><header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur"><div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4"><a href="./" class="display text-2xl font-bold text-[#17457c]">YEB<span class="text-[#fca311]">.</span></a><nav class="flex items-center gap-4">' . $nav . '</nav></div></header><main class="mx-auto max-w-6xl px-5 md:px-8">' . $content . '</main><footer class="border-t bg-white"><div class="mx-auto max-w-6xl px-5 py-6 text-sm text-slate-500">YEB · Youth Employment Bridge · Conectando talento y oportunidades</div></footer></body></html>';
    }

    private static function alerts(?string $message, ?string $error): string { return ($error ? '<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">' . self::e($error) . '</div>' : '') . ($message ? '<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">' . self::e($message) . '</div>' : ''); }
    private static function field(string $name, string $label, string $type = 'text'): string { return $type === 'textarea' ? '<label class="mt-4 block text-sm font-bold">' . $label . '<textarea required name="' . $name . '" class="mt-1 min-h-24 w-full rounded-lg border p-3"></textarea></label>' : '<label class="mt-4 block text-sm font-bold">' . $label . '<input required name="' . $name . '" type="' . $type . '" class="mt-1 w-full rounded-lg border p-3"></label>'; }
    private static function fieldValue(string $name, string $label, ?string $value, string $type = 'text'): string
    {
        $value = self::e($value ?? '');
        if ($type === 'textarea') return '<label class="text-sm font-bold md:col-span-2">' . $label . '<textarea name="' . $name . '" class="mt-1 min-h-24 w-full rounded-lg border p-3">' . $value . '</textarea></label>';
        return '<label class="text-sm font-bold">' . $label . '<input name="' . $name . '" type="' . $type . '" value="' . $value . '" class="mt-1 w-full rounded-lg border p-3"></label>';
    }
    private static function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
