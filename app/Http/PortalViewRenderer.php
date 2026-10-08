<?php

namespace App\Http;

use Modules\Applications\Controllers\ApplicationsController;
use Modules\Companies\Services\CompanyProfileService;
use Modules\Dashboard\Controllers\DashboardController;
use Modules\Jobs\Controllers\JobsController;
use Modules\Notifications\Controllers\NotificationsController;
use Modules\Search\Controllers\SearchController;
use Modules\Users\Controllers\UserController;

final class PortalViewRenderer
{
    public static function dashboard(array $user, ?string $message, ?string $error, array $searchJobs, DashboardController $dashboardController, SearchController $searchController): string
    {
        if ($user['role'] === 'admin') {
            $summary = $dashboardController->summary();
            $cards = '';
            foreach ($summary['counts'] as $label => $count) {
                $cards .= '<div class="rounded-2xl border bg-white p-5"><p class="text-sm capitalize text-slate-500">' . self::e($label) . '</p><p class="mt-2 text-3xl font-bold">' . $count . '</p></div>';
            }

            $auditRows = '';
            foreach ($summary['logs'] as $log) {
                $auditRows .= '<tr class="border-t"><td class="px-3 py-3 text-xs">' . self::e($log['created_at']) . '</td><td class="px-3 py-3">' . self::e($log['user_name'] ?? 'Sistema') . '</td><td class="px-3 py-3 font-bold">' . self::e($log['action']) . '</td><td class="px-3 py-3">' . self::e($log['module']) . '</td><td class="px-3 py-3">' . self::e($log['description']) . '</td><td class="px-3 py-3">' . self::e($log['ip_address']) . '</td></tr>';
            }

            return self::alerts($message, $error)
                . '<section class="candidate-page" data-modules="admin-dashboard" data-role="admin">'
                . '<div class="mb-8"><p class="font-bold uppercase tracking-widest text-[#fca311]">Administración</p><h1 class="display text-4xl font-bold">Panel de auditoría</h1><p class="mt-2 text-slate-500">Métricas y control operativo del portal.</p></div>'
                . '<div id="admin-summary-cards" class="mb-8 grid gap-4 md:grid-cols-4">' . $cards . '</div>'
                . '<div class="mb-6 grid gap-5 lg:grid-cols-2">'
                . '<article class="profile-card"><div class="flex items-center justify-between"><h2>Moderación de empresas</h2><button class="filter-pill" data-admin-refresh-companies type="button">Actualizar</button></div><div id="admin-companies-list" class="mt-4 text-sm text-slate-700"></div></article>'
                . '<article class="profile-card"><div class="flex items-center justify-between"><h2>Moderación de usuarios</h2><button class="filter-pill" data-admin-refresh-users type="button">Actualizar</button></div><p class="mt-3 text-sm text-amber-700">La suspensión de usuarios está deshabilitada en este esquema.</p><div id="admin-users-list" class="mt-4 text-sm text-slate-700"></div></article>'
                . '</div>'
                . '<div class="overflow-x-auto rounded-2xl border bg-white p-4"><div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 class="display text-2xl font-bold">Logs recientes</h2><form id="audit-filters" class="flex gap-2"><input name="module" placeholder="Módulo" class="rounded-lg border px-3 py-2 text-sm"><select name="status" class="rounded-lg border px-3 py-2 text-sm"><option value="">Todos</option><option value="success">Éxito</option><option value="failed">Fallido</option><option value="error">Error</option></select><button class="filter-pill" type="submit">Filtrar</button></form></div><table class="w-full min-w-[850px] text-left text-sm"><thead><tr class="text-xs uppercase text-slate-500"><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Usuario</th><th class="px-3 py-2">Acción</th><th class="px-3 py-2">Módulo</th><th class="px-3 py-2">Descripción</th><th class="px-3 py-2">IP</th></tr></thead><tbody id="audit-log-rows">' . $auditRows . '</tbody></table><div class="mt-3"><button type="button" id="audit-load-more" class="filter-pill">Cargar más</button></div></div>'
                . '</section>';
        }

        return self::candidateDashboard($user, $message, $error, $searchJobs, $searchController);
    }

    private static function companyNav(string $active): string
    {
        $items = ['dashboard' => '▦ Resumen', 'company_profile' => '◉ Perfil de empresa', 'company_jobs' => '▤ Mis vacantes', 'company_applications' => '♙ Candidatos'];
        $html = '<nav class="profile-tabs company-tabs">';
        foreach ($items as $key => $label) {
            $html .= '<a class="' . ($key === $active ? 'active' : '') . '" href="?action=' . $key . '">' . $label . '</a>';
        }
        return $html . '</nav>';
    }

    public static function companyArea(array $user, string $action, ?string $message, ?string $error, CompanyProfileService $companies, JobsController $jobsController, ApplicationsController $applicationsController): string
    {
        $company = $companies->find($user['company_id']);
        if (!$company) {
            return self::alerts($message, 'No se encontró la empresa asociada a la cuenta.');
        }

        $jobList = $jobsController->byCompany($user['company_id']);
        $applicationList = $applicationsController->byCompany($user['company_id']);
        $profilePhoto = $company['profile_photo_path']
            ? '<img src="?action=download_profile_photo&type=company&id=' . $user['company_id'] . '" alt="Logo" class="company-logo">'
            : '<div class="company-logo company-logo-empty">' . strtoupper(substr($company['name'], 0, 1)) . '</div>';

        $content = self::alerts($message, $error)
            . '<section class="candidate-page company-page" data-role="recruiter" data-company-id="' . (int) $user['company_id'] . '">'
            . self::companyNav($action)
            . '<div class="company-heading"><div>' . $profilePhoto . '</div><div><p class="eyebrow">Panel de empresa</p><h1>' . self::e($company['name']) . '</h1><p>' . self::e($company['industry'] ?: 'Empresa reclutadora') . ' · ' . self::e($company['city']) . '</p></div></div>';

        if ($action === 'company_profile') {
            $content .= self::companyProfileView($company, $user);
        } elseif ($action === 'company_jobs') {
            $content .= self::companyJobsView($jobList, $user);
        } elseif ($action === 'company_applications') {
            $content .= self::companyApplicationsView($applicationList, $user);
        } else {
            $published = count(array_filter($jobList, fn(array $job): bool => $job['status'] === 'published'));
            $pending = count(array_filter($applicationList, fn(array $item): bool => in_array($item['status'], ['pending', 'review'], true)));
            $cards = '<div class="company-metrics"><div><span>Vacantes publicadas</span><strong>' . $published . '</strong></div><div><span>Total de vacantes</span><strong>' . count($jobList) . '</strong></div><div><span>Candidatos pendientes</span><strong>' . $pending . '</strong></div><div><span>Postulaciones recibidas</span><strong>' . count($applicationList) . '</strong></div></div>';
            $recent = '';
            foreach (array_slice($applicationList, 0, 5) as $item) {
                $recent .= '<a class="application-card" href="?action=company_applications"><div><h2>' . self::e($item['candidate_name']) . '</h2><p>' . self::e($item['title']) . '</p></div><strong>' . self::e($item['status']) . '</strong></a>';
            }
            $content .= $cards . '<div class="company-columns"><article class="profile-card"><div class="flex items-center justify-between"><h2>Actividad reciente</h2><a class="profile-link" href="?action=company_applications">Ver candidatos →</a></div>' . ($recent ?: '<p class="mt-5 text-slate-500">Aún no has recibido postulaciones.</p>') . '</article><article class="premium-card company-cta"><h2>Publica una nueva vacante</h2><p>Encuentra talento y gestiona todo el proceso desde un solo lugar.</p><a href="?action=company_jobs#crear">Crear vacante</a></article></div>';
        }

        return $content . '</section>';
    }

    private static function companyProfileView(array $company, array $user): string
    {
        return '<article class="profile-card company-section" data-modules="companies"><div class="flex items-center justify-between"><h2>Información corporativa</h2><span class="verified-badge">' . ((int) $company['verified'] === 1 ? '✓ Empresa verificada' : 'Pendiente de verificación') . '</span></div><p class="section-copy">Mantén actualizados los datos que verán los candidatos.</p><form id="company-profile-form" method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre de empresa', $company['name']) . self::fieldValue('city', 'Ciudad', $company['city']) . self::fieldValue('industry', 'Sector', $company['industry']) . self::fieldValue('size', 'Tamaño', $company['size']) . self::fieldValue('website', 'Sitio web', $company['website'], 'url') . self::fieldValue('phone', 'Teléfono', $company['phone']) . self::fieldValue('contact_email', 'Correo de contacto', $company['contact_email'], 'email') . self::fieldValue('description', 'Descripción', $company['description'], 'textarea') . '<label>Logo o foto corporativa<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><button class="primary-button" data-loading-text="Guardando...">Guardar perfil corporativo</button></form></article>';
    }

    private static function companyJobsView(array $jobs, array $user): string
    {
        $rows = '';
        foreach ($jobs as $job) {
            $payload = self::e(json_encode([
                'id' => (int) $job['id'],
                'title' => (string) $job['title'],
                'description' => (string) $job['description'],
                'city' => (string) $job['city'],
                'work_mode' => $job['work_mode'] === 'Remote' ? 'remote' : ($job['work_mode'] === 'Presencial' ? 'onsite' : 'hybrid'),
                'experience' => (string) $job['experience'],
                'salary_min' => (string) $job['salary_min'],
                'salary_max' => (string) $job['salary_max'],
                'status' => (string) $job['status'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

            $rows .= '<article class="application-card" data-job-row data-job-id="' . (int) $job['id'] . '" data-job-payload="' . $payload . '"><div><h2>' . self::e($job['title']) . '</h2><p>' . self::e($job['city']) . ' · ' . self::e($job['work_mode']) . '</p></div><div class="text-right"><strong>' . self::e($job['status']) . '</strong><p class="text-sm text-slate-500">' . $job['applications_count'] . ' candidatos</p><div class="mt-2 flex gap-2"><button type="button" class="filter-pill" data-job-edit="' . (int) $job['id'] . '">Editar</button><button type="button" class="filter-pill" data-job-toggle-status="' . (int) $job['id'] . '" data-status-target="closed">Pausar</button><button type="button" class="filter-pill" data-job-delete="' . (int) $job['id'] . '">Eliminar</button></div></div></article>';
        }

        return '<div class="company-columns" id="crear" data-modules="jobs"><form id="job-create-form" method="post" action="?action=create_job" class="profile-card company-section"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="" data-job-id-field><h2>Crear o editar vacante</h2><p class="section-copy">Publica oportunidades claras y atractivas para los candidatos.</p>' . self::field('title', 'Título') . self::field('description', 'Descripción', 'textarea') . self::field('city', 'Ciudad') . self::field('experience', 'Experiencia') . '<div class="grid grid-cols-2 gap-3">' . self::field('salary_min', 'Salario mínimo', 'number') . self::field('salary_max', 'Salario máximo', 'number') . '</div><label class="mt-4 block text-sm font-bold">Modalidad<select name="work_mode" class="mt-1 w-full rounded-lg border p-3"><option value="hybrid">Híbrido</option><option value="remote">Remoto</option><option value="onsite">Presencial</option></select></label><label class="mt-4 block text-sm font-bold">Estado<select name="status" class="mt-1 w-full rounded-lg border p-3"><option value="published">Publicada</option><option value="draft">Borrador</option><option value="closed">Pausada</option></select></label><div class="mt-4 flex gap-2"><button class="primary-button" data-job-submit data-loading-text="Guardando...">Guardar vacante</button><button type="button" class="filter-pill" data-job-reset>Limpiar</button></div></form><article class="profile-card company-section"><h2>Mis vacantes</h2><div id="company-jobs-list" class="mt-5">' . ($rows ?: '<p class="text-slate-500">Aún no tienes vacantes.</p>') . '</div></article></div>';
    }

    private static function companyApplicationsView(array $applications, array $user): string
    {
        $rows = '';
        foreach ($applications as $item) {
            $cv = $item['cv_original_name']
                ? '<a class="profile-link" href="?action=download_cv&candidate=' . $item['candidate_id'] . '">Ver hoja de vida</a>'
                : '<span class="text-slate-400">Sin CV</span>';

            $rows .= '<article class="application-card"><div><h2>' . self::e($item['candidate_name']) . '</h2><p>' . self::e($item['email']) . ' · ' . self::e($item['title']) . '</p>' . $cv . '</div><form method="post" action="?action=update_application" class="flex items-center gap-2 js-application-status-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="application_id" value="' . $item['id'] . '"><select name="status" class="rounded-lg border p-2"><option value="pending"' . ($item['status'] === 'pending' ? ' selected' : '') . '>Pendiente</option><option value="review"' . ($item['status'] === 'review' ? ' selected' : '') . '>En revisión</option><option value="interview"' . ($item['status'] === 'interview' ? ' selected' : '') . '>Entrevista</option><option value="accepted"' . ($item['status'] === 'accepted' ? ' selected' : '') . '>Aceptado</option><option value="rejected"' . ($item['status'] === 'rejected' ? ' selected' : '') . '>Rechazado</option></select><button class="primary-button" data-loading-text="Actualizando...">Actualizar</button></form></article>';
        }

        return '<article class="profile-card company-section" data-modules="applications"><div class="flex items-center justify-between"><div><h2>Candidatos</h2><p class="section-copy">Revisa perfiles, descarga hojas de vida y actualiza cada proceso.</p></div><span class="company-count"><span data-application-total>' . count($applications) . '</span> postulaciones</span></div><div id="company-applications-list" class="mt-5">' . ($rows ?: '<p class="text-slate-500">No hay postulaciones recibidas.</p>') . '</div></article>';
    }

    public static function candidateDashboard(array $user, ?string $message, ?string $error, array $searchJobs, SearchController $searchController): string
    {
        $keyword = self::e($_GET['q'] ?? '');
        $location = self::e($_GET['location'] ?? '');
        $hiddenIds = $searchController->hiddenJobIds($user['id']);
        $savedIds = $searchController->savedJobIds($user['id']);
        $jobs = array_values(array_filter($searchJobs, fn(array $job): bool => !in_array((int) $job['id'], $hiddenIds, true)));

        $jobCards = '';
        foreach ($jobs as $index => $job) {
            $isSaved = in_array((int) $job['id'], $savedIds, true);
            $jobCards .= '<article class="block border-b border-slate-200 bg-white p-5 transition hover:bg-slate-50" data-job-card data-job-id="' . (int) $job['id'] . '"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-[#c64a44]">Nueva oportunidad</p><h3 class="mt-2 text-lg font-bold text-[#25364b]">' . self::e($job['title']) . '</h3><p class="mt-1 text-sm font-medium text-slate-600">' . self::e($job['company_name']) . '</p></div><span class="text-lg ' . ($isSaved ? 'text-[#17457c]' : 'text-slate-300') . '">' . ($isSaved ? '♥' : '♡') . '</span></div><p class="mt-3 text-sm text-slate-600">' . self::e($job['city']) . ' · ' . self::e($job['work_mode']) . '</p><div class="mt-4 flex items-center justify-between text-xs text-slate-500"><span>' . self::e($job['experience']) . '</span><span>' . date('d M', strtotime($job['created_at'])) . '</span></div></article>';
            if ($index >= 14) {
                break;
            }
        }

        $firstJob = $jobs[0] ?? null;
        $detail = $firstJob
            ? '<div id="job-detail" data-selected-job-id="' . (int) $firstJob['id'] . '"><h1 class="mt-3 text-3xl font-bold text-[#25364b]" data-job-title>' . self::e($firstJob['title']) . '</h1><p class="mt-2 text-lg font-semibold text-[#25364b]" data-job-company>' . self::e($firstJob['company_name']) . '</p><p class="mt-2 text-slate-600" data-job-location>' . self::e($firstJob['city']) . '</p><div class="mt-7 flex flex-wrap gap-3"><button class="rounded-full bg-[#17457c] px-8 py-3 font-bold text-white shadow-sm transition hover:bg-[#12365f]" type="button" data-apply-job="' . (int) $firstJob['id'] . '">Aplicar</button><button class="circle-action" type="button" data-save-job="' . (int) $firstJob['id'] . '" title="Guardar vacante">' . (in_array((int) $firstJob['id'], $savedIds, true) ? '♥' : '♡') . '</button><button class="circle-action" type="button" data-hide-job="' . (int) $firstJob['id'] . '" title="Ocultar vacante">◉</button><button class="circle-action" type="button" data-share-job title="Compartir vacante">↗</button></div><div class="my-7 border-t border-slate-200"></div><p class="whitespace-pre-line leading-7 text-slate-700" data-job-description>' . self::e($firstJob['description']) . '</p></div>'
            : '<div class="flex h-full items-center justify-center p-12 text-center text-slate-500" id="job-detail-empty">No hay vacantes con estos filtros.</div>';

        return self::alerts($message, $error)
            . '<section class="dashboard-shell -mx-5 min-h-[calc(100vh-78px)] bg-[#eef5fa] md:-mx-8" data-modules="search applications" data-role="candidate">'
            . '<div class="border-b border-slate-200 bg-[#f5fbff] px-5 py-5 md:px-8">'
            . '<form id="candidate-search-form" method="get" class="mx-auto grid max-w-7xl gap-3 md:grid-cols-2 lg:grid-cols-6"><input type="hidden" name="action" value="dashboard"><div class="search-field flex-1"><span>▣</span><input name="q" value="' . $keyword . '" placeholder="Cargo o categoría"></div><div class="search-field flex-1"><span>⌖</span><input name="location" value="' . $location . '" placeholder="Lugar"></div><input name="salary_min" type="number" min="0" placeholder="Salario mín." class="rounded-full border px-4 py-3"><input name="salary_max" type="number" min="0" placeholder="Salario máx." class="rounded-full border px-4 py-3"><select name="work_mode" class="rounded-full border px-4 py-3"><option value="">Modalidad</option><option value="Remote">Remoto</option><option value="Hybrid">Híbrido</option><option value="Presencial">Presencial</option></select><select name="experience" class="rounded-full border px-4 py-3"><option value="">Experiencia</option><option value="Inicial">Inicial</option><option value="Junior">Junior</option><option value="Semi Senior">Semi Senior</option><option value="Senior">Senior</option></select><button class="rounded-full bg-[#17457c] px-7 py-3 font-bold text-white lg:col-span-2" type="submit" data-loading-text="Buscando...">⌕ Buscar empleos</button><button type="button" class="rounded-full border border-slate-300 px-7 py-3 font-bold text-slate-500 lg:col-span-2" disabled title="Filtro de contrato no disponible en el esquema actual">Contrato (no disponible)</button></form>'
            . '</div>'
            . '<div class="mx-auto grid max-w-7xl lg:grid-cols-[minmax(330px,39%)_1fr]">'
            . '<aside class="max-h-[calc(100vh-210px)] overflow-y-auto border-r border-slate-200"><div class="border-b border-slate-200 bg-white px-5 py-5"><p class="text-xl font-bold text-[#25364b]"><strong data-search-total>' . count($jobs) . '</strong> ofertas disponibles</p><p class="mt-1 text-sm text-slate-500">Selecciona una oferta para ver sus detalles.</p></div><div id="search-results" data-loading-target="search">' . $jobCards . '</div><div class="border-t bg-white p-4"><button type="button" id="search-load-more" class="filter-pill">Cargar más resultados</button></div></aside>'
            . '<article class="min-h-[calc(100vh-210px)] bg-white p-6 md:p-10">' . $detail . '</article>'
            . '</div>'
            . '</section>';
    }

    private static function candidateNav(string $active): string
    {
        $items = ['dashboard' => '⌂ Mi área', 'profile' => '▣ Hoja de Vida', 'applications' => '➤ Aplicaciones', 'alerts' => '♧ Mis alertas', 'favorites' => '♡ Mis favoritos'];
        $html = '<nav class="profile-tabs">';
        foreach ($items as $key => $label) {
            $html .= '<a class="' . ($key === $active ? 'active' : '') . '" href="?action=' . $key . '">' . $label . '</a>';
        }
        return $html . '</nav>';
    }

    public static function candidateArea(array $user, ?string $message, ?string $error, array $jobs, UserController $userController, ApplicationsController $applicationsController): string
    {
        $profile = $userController->profile($user['id']);
        if (!$profile) {
            return self::alerts($message, 'No se encontró el perfil del usuario.');
        }

        $counts = array_fill_keys(['pending', 'review', 'interview', 'accepted', 'rejected'], 0);
        foreach ($applicationsController->countByStatus($user['id']) as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        $completed = 30 + ($profile['cv_path'] ? 25 : 0) + ($profile['professional_title'] ? 15 : 0) + ($profile['skills'] ? 15 : 0) + ($profile['education'] ? 15 : 0);
        $recommendations = '';
        foreach (array_slice($jobs, 0, 4) as $job) {
            $recommendations .= '<a href="?action=search&job=' . $job['id'] . '" class="profile-job"><p class="text-xs font-bold uppercase text-[#c64a44]">Oportunidad recomendada</p><h3>' . self::e($job['title']) . '</h3><p>' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p><span>$' . number_format((float) $job['salary_min'], 0, ',', '.') . '</span></a>';
        }

        $photo = $profile['profile_photo_path']
            ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto de perfil" class="profile-avatar">'
            : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';

        return self::alerts($message, $error)
            . '<section class="candidate-page">'
            . self::candidateNav('dashboard')
            . '<div class="profile-grid"><div><article class="profile-card"><div class="flex items-center gap-5">' . $photo . '<div><h1>' . self::e($profile['name']) . '</h1><p>' . self::e($profile['professional_title'] ?: 'Completa tu titular profesional') . '</p><p class="profile-completion">Perfil completado <strong>' . $completed . '%</strong></p></div><a class="ml-auto text-2xl" href="?action=profile">›</a></div><div class="progress-track"><span style="width:' . $completed . '%"></span></div><div class="stats-grid"><div><b>◷</b><span>Pendiente</span><strong>' . $counts['pending'] . '</strong></div><div><b>▣</b><span>En revisión</span><strong>' . $counts['review'] . '</strong></div><div><b>⌁</b><span>Entrevista</span><strong>' . $counts['interview'] . '</strong></div><div><b>✓</b><span>Aceptadas</span><strong>' . $counts['accepted'] . '</strong></div></div></article><article class="profile-card mt-6"><h2>Accesos rápidos</h2><div class="quick-links"><a href="?action=search">Buscar empleo <span>Explora nuevas vacantes</span>→</a><a href="?action=applications">Ver aplicaciones <span>Revisa tus procesos</span>→</a><a href="?action=favorites">Mis favoritos <span>Vacantes guardadas</span>→</a></div></article></div><aside class="profile-card recommendations"><h2>Recomendadas para <strong>ti</strong></h2>' . ($recommendations ?: '<p class="p-5 text-slate-500">No hay recomendaciones por ahora.</p>') . '</aside></div></section>';
    }

    public static function candidateProfile(array $user, ?string $message, ?string $error, UserController $userController): string
    {
        $profile = $userController->profile($user['id']);
        if (!$profile) {
            return self::alerts($message, 'No se encontró el perfil del usuario.');
        }

        $cv = $profile['cv_original_name'] ? '<p class="text-emerald-700">' . self::e($profile['cv_original_name']) . ' · <a class="font-bold" href="?action=download_cv&candidate=' . $user['id'] . '">Descargar</a></p>' : '<p class="text-slate-500">No has cargado una hoja de vida.</p>';
        $photo = $profile['profile_photo_path'] ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto" class="profile-avatar">' : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';
        $form = '<form id="candidate-profile-form" method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form" data-modules="applications"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre completo', $profile['name']) . self::fieldValue('professional_title', 'Titular profesional', $profile['professional_title']) . self::fieldValue('phone', 'Teléfono', $profile['phone']) . self::fieldValue('location', 'Ubicación', $profile['location']) . self::fieldValue('availability', 'Disponibilidad', $profile['availability']) . self::fieldValue('linkedin_url', 'LinkedIn', $profile['linkedin_url'], 'url') . self::fieldValue('portfolio_url', 'Portafolio', $profile['portfolio_url'], 'url') . self::fieldValue('skills', 'Conocimientos y habilidades', $profile['skills'], 'textarea') . self::fieldValue('experience', 'Experiencia profesional', $profile['experience'], 'textarea') . self::fieldValue('education', 'Educación', $profile['education'], 'textarea') . self::fieldValue('bio', 'Resumen profesional', $profile['bio'], 'textarea') . '<label>Foto de perfil<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><button class="primary-button" data-loading-text="Guardando...">Guardar cambios</button></form>';

        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('profile') . '<h1 class="page-title">Hoja de Vida</h1><div class="profile-layout"><div><article class="profile-card profile-identity"><div class="flex items-center gap-5">' . $photo . '<div><h2>' . self::e($profile['name']) . '</h2><p>' . self::e($profile['location'] ?: 'Agrega tu ubicación') . '</p><p>' . self::e($profile['phone'] ?: 'Agrega tu teléfono') . '</p></div></div><div class="mt-8">' . $form . '</div></article><article class="profile-card mt-6" data-modules="applications"><h2>Documentos adjuntos</h2><div class="mt-4">' . $cv . '</div><form id="candidate-cv-form" method="post" action="?action=upload_cv" enctype="multipart/form-data" class="mt-5 flex gap-3"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required type="file" name="cv" accept=".pdf,.doc,.docx" class="flex-1 rounded-lg border p-3"><button class="primary-button" data-loading-text="Subiendo...">Subir o modificar CV</button></form></article></div><aside class="profile-card side-card"><h2>Tu perfil profesional</h2><p>Una información completa ayuda a las empresas a conocerte mejor.</p><a href="?action=dashboard">Volver a Mi área</a></aside></div></section>';
    }

    public static function candidateApplications(array $user, ?string $message, ?string $error, ApplicationsController $applicationsController): string
    {
        $rows = '';
        foreach ($applicationsController->byUser($user['id']) as $item) {
            $rows .= '<article class="application-card"><div><h2>' . self::e($item['title']) . '</h2><p>' . self::e($item['company_name']) . ' · ' . self::e($item['city']) . '</p></div><strong>' . self::e($item['status']) . '</strong></article>';
        }

        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('applications') . '<h1 class="page-title">Mis aplicaciones</h1><div class="application-list">' . ($rows ?: '<div class="profile-card p-8 text-slate-500">Aún no tienes aplicaciones.</div>') . '</div></section>';
    }

    public static function candidateFavorites(array $user, ?string $message, ?string $error, SearchController $searchController): string
    {
        $rows = '';
        foreach ($searchController->savedJobs($user['id']) as $job) {
            $rows .= '<a class="application-card" href="?action=search&job=' . $job['id'] . '"><div><h2>' . self::e($job['title']) . '</h2><p>' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p></div><span class="text-xl text-pink-500">♥</span></a>';
        }

        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('favorites') . '<h1 class="page-title">Mis favoritos</h1><div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><p class="text-5xl">♡</p><h2>Todavía no tienes ofertas guardadas</h2><a href="?action=search" class="primary-button">Buscar empleos</a></div>') . '</div></section>';
    }

    public static function candidateAlerts(array $user, ?string $message, ?string $error, NotificationsController $notificationsController): string
    {
        $rows = '';
        foreach ($notificationsController->forUser($user['id']) as $item) {
            $rows .= '<article class="application-card"><div><h2>' . self::e($item['message']) . '</h2><p>' . self::e($item['created_at']) . '</p></div><span>♧</span></article>';
        }

        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('alerts') . '<h1 class="page-title">Mis alertas</h1><div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><p class="text-5xl">♧</p><h2>No tienes alertas todavía</h2><p>Pronto recibirás recomendaciones relacionadas con tu perfil.</p></div>') . '</div></section>';
    }

    public static function home(array $jobs, ?array $user, string $action, ?string $message, ?string $error): string
    {
        $alerts = self::alerts($message, $error);
        if ($action === 'login' || $action === 'register') {
            return $alerts . '<section class="mx-auto max-w-md rounded-2xl border bg-white p-8 shadow-sm"><h1 class="display mb-6 text-4xl font-bold">' . ($action === 'login' ? 'Ingresar' : 'Crear perfil') . '</h1><form method="post" class="space-y-4"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . ($action === 'register' ? '<input required name="name" placeholder="Nombre completo" class="w-full rounded-lg border p-3"><input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3">' : '<input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3">') . '<input required name="password" type="password" placeholder="Contraseña" class="w-full rounded-lg border p-3"><button class="w-full rounded-lg bg-blue-600 p-3 font-bold text-white">Continuar</button></form></section>';
        }

        $cards = '';
        foreach ($jobs as $job) {
            $cards .= '<article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"><span class="rounded-full bg-[#eef3fa] px-3 py-1 text-xs font-bold text-[#17457c]">' . self::e($job['work_mode']) . '</span><h3 class="mt-5 text-xl font-bold">' . self::e($job['title']) . '</h3><p class="mt-2 text-sm font-medium text-slate-500">' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p><p class="mt-4 text-sm leading-6 text-slate-600">' . self::e($job['description']) . '</p><div class="mt-6 flex items-center justify-between border-t pt-4"><span class="text-sm font-bold">$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.') . '</span>' . ($user && $user['role'] === 'candidate' ? '<form method="post" action="?action=apply" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="' . $job['id'] . '"><button class="font-bold text-[#17457c]">Postularme →</button></form>' : '<a href="?action=login" class="font-bold text-[#17457c]">Ingresar →</a>') . '</div></article>';
        }

        return $alerts . '<section class="landing-hero relative -mx-5 overflow-hidden px-5 py-16 text-white md:-mx-8 md:px-8 md:py-24"><div class="absolute inset-0 bg-[#102e50]/55"></div><div class="relative mx-auto max-w-6xl"><div class="max-w-3xl"><p class="mb-5 text-sm font-bold uppercase tracking-[.24em] text-[#fca311]">Youth Employment Bridge</p><h1 class="display text-5xl font-bold leading-[1.05] md:text-7xl">El próximo paso de tu carrera empieza aquí.</h1><p class="mt-6 max-w-2xl text-lg leading-8 text-white/90 md:text-xl">Encuentra oportunidades que encajen contigo y conecta con empresas que buscan tu talento.</p></div><form class="relative z-10 mt-10 grid gap-3 rounded-2xl bg-white p-3 shadow-2xl md:grid-cols-[1fr_1fr_auto]" method="get"><div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4"><span class="text-xl text-[#17457c]">⌕</span><input name="q" value="' . self::e($_GET['q'] ?? '') . '" placeholder="Cargo, habilidad o empresa" class="w-full py-3 text-[#14213d] outline-none"></div><div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4"><span class="text-xl text-[#17457c]">⌖</span><input name="location" value="' . self::e($_GET['location'] ?? '') . '" placeholder="Ciudad o modalidad" class="w-full py-3 text-[#14213d] outline-none"></div><button class="rounded-xl bg-[#17457c] px-7 py-3 font-bold text-white transition hover:bg-[#12365f]">Buscar empleos</button></form></div></section><section class="mx-auto max-w-6xl py-10"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-bold uppercase tracking-[.18em] text-[#17457c]">Explora oportunidades</p><h2 class="display mt-2 text-3xl font-bold">Vacantes para ti</h2></div><span class="text-sm text-slate-500">' . count($jobs) . ' resultados disponibles</span></div><div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">' . $cards . '</div></section><section class="border-t border-slate-200 py-10"><div class="grid gap-4 md:grid-cols-3"><a href="?action=register" class="rounded-2xl bg-[#14213d] p-6 text-white transition hover:-translate-y-1"><span class="text-2xl">✦</span><h3 class="mt-4 text-xl font-bold">Crea tu perfil</h3><p class="mt-2 text-sm text-white/70">Presenta tu experiencia y conecta con nuevas oportunidades.</p></a><a href="?action=login" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-lg"><span class="text-2xl text-[#fca311]">▣</span><h3 class="mt-4 text-xl font-bold">Sigue tus postulaciones</h3><p class="mt-2 text-sm text-slate-500">Consulta el estado de cada proceso desde tu dashboard.</p></a><a href="?action=register" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-lg"><span class="text-2xl text-[#17457c]">⌁</span><h3 class="mt-4 text-xl font-bold">Publica como empresa</h3><p class="mt-2 text-sm text-slate-500">Encuentra candidatos y gestiona tus vacantes.</p></a></div></section>';
    }

    public static function layout(string $content, ?array $user): string
    {
        $nav = $user
            ? '<a href="?action=dashboard" class="font-bold">' . self::e($user['name']) . '</a><form method="post" action="?action=logout" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><button class="rounded-full border border-slate-200 px-4 py-2">Salir</button></form>'
            : '<a href="#vacantes" class="hidden text-sm font-medium md:inline">Buscar ofertas</a><a href="?action=login" class="text-sm font-medium">Ingresar</a><a href="?action=register" class="rounded-full bg-[#17457c] px-5 py-2.5 text-sm font-bold text-white">Crear perfil</a>';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>YEB | Youth Employment Bridge</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"><style>body{font-family:DM Sans,sans-serif}.display{font-family:Fraunces,serif}.landing-hero{background-image:url("https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=2200&q=85");background-position:center;background-size:cover}.search-field{display:flex;align-items:center;gap:.75rem;border:1px solid #dbe4ec;border-radius:999px;background:#fff;padding:.25rem 1rem;color:#17457c}.search-field input{width:100%;background:transparent;padding:.65rem .25rem;outline:none}.filter-pill{white-space:nowrap;border:1px solid #dbe4ec;border-radius:.7rem;background:#fff;padding:.55rem 1rem;font-size:.85rem;font-weight:700;color:#40536a}.circle-action{display:inline-flex;height:3.25rem;width:3.25rem;align-items:center;justify-content:center;border-radius:999px;background:#edf5fc;color:#17457c;font-size:1.25rem;transition:background .2s}.circle-action:hover{background:#dbeaf7}.candidate-page{min-height:calc(100vh - 90px);background:#eef5fa;padding:1.5rem max(1rem,4vw) 4rem;margin:0 -2rem}.profile-tabs{display:flex;gap:.25rem;overflow:auto;border-radius:999px;background:#e5f0f9;padding:.3rem;margin-bottom:2rem}.profile-tabs a{white-space:nowrap;border-radius:999px;padding:.75rem 1.4rem;color:#587087;font-size:.9rem;font-weight:700}.profile-tabs a.active{background:#17457c;color:#fff;box-shadow:0 3px 10px #17457c30}.profile-grid{display:grid;grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr);gap:2rem;max-width:1180px;margin:auto}.profile-card,.premium-card{border-radius:1rem;background:#fff;padding:1.6rem;box-shadow:0 7px 20px #23415a0d}.profile-card h1{font-size:1.35rem;font-weight:700;color:#25364b}.profile-card h2{font-size:1.25rem;font-weight:700;color:#25364b}.profile-card p{color:#64788d}.profile-avatar{height:6.2rem;width:6.2rem;border-radius:999px;object-fit:cover;border:5px solid #7abf4d}.profile-avatar-empty{display:flex;align-items:center;justify-content:center;background:#14213d;color:#fff;font-size:2rem}.profile-completion{margin-top:.4rem}.profile-completion strong{color:#68a839}.progress-track{height:.5rem;border-radius:999px;background:#e9eef2;margin-top:1.4rem;overflow:hidden}.progress-track span{display:block;height:100%;border-radius:inherit;background:#7abf4d}.stats-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-top:1.2rem}.stats-grid div{display:grid;grid-template-columns:2.2rem 1fr auto;align-items:center;gap:.5rem;color:#52697e}.stats-grid b{display:grid;place-items:center;height:2.2rem;border-radius:50%;background:#edf5fc;color:#17457c}.stats-grid strong{color:#25364b}.profile-link{display:block;margin-top:1.3rem;color:#17457c;font-weight:700}.quick-links{display:grid;margin-top:1rem}.quick-links a{display:flex;align-items:center;gap:1rem;border-top:1px solid #edf0f3;padding:1rem 0;color:#17457c;font-size:1.05rem}.quick-links span{flex:1;color:#50677c;font-size:.95rem}.recommendations{padding:0;overflow:hidden}.recommendations h2{padding:1.5rem;border-bottom:1px solid #e7edf2;font-size:1.2rem;font-weight:400}.recommendations h2 strong{color:#17457c}.profile-job{display:block;border-bottom:1px solid #edf0f3;padding:1.25rem 1.5rem}.profile-job h3{margin-top:.4rem;font-size:1.05rem;font-weight:700;color:#25364b}.profile-job p{margin-top:.25rem;font-size:.9rem}.profile-job span{display:block;margin-top:.7rem;font-size:.9rem;font-weight:700;color:#17457c}.premium-card{background:#57b9aa;color:#fff;text-align:center}.premium-card h2{color:#fff}.premium-card p{margin:1rem 0;color:#effffb}.premium-card a,.primary-button{display:inline-block;border-radius:999px;background:#17457c;padding:.8rem 1.5rem;color:#fff;font-weight:700}.profile-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(250px,.5fr);gap:2rem;max-width:1180px;margin:auto}.page-title{max-width:1180px;margin:0 auto 1.2rem;font-size:2rem;color:#25364b}.profile-form{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-top:2rem}.profile-form label{display:block;color:#344d63;font-size:.9rem;font-weight:700}.profile-form label:nth-last-of-type(3),.profile-form label:nth-last-of-type(2),.profile-form label:nth-last-of-type(1){grid-column:1/-1}.profile-form input,.profile-form textarea,.profile-form select{display:block;width:100%;margin-top:.35rem;border:1px solid #dbe4ec;border-radius:.6rem;padding:.75rem}.profile-form textarea{min-height:6rem}.profile-form .primary-button{border:0}.application-list{max-width:900px;margin:auto}.application-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;border-radius:1rem;background:#fff;padding:1.4rem 1.6rem;box-shadow:0 7px 20px #23415a0d}.application-card h2{font-size:1.05rem;font-weight:700;color:#25364b}.application-card p{margin-top:.3rem;color:#64788d}.application-card strong{color:#17457c}.empty-state{text-align:center;padding:4rem}.empty-state h2{margin:1rem 0 1.5rem;font-size:1.3rem;color:#25364b}.side-card{align-self:start}.toast{border-radius:.75rem;padding:.75rem 1rem;background:#14213d;color:#fff;margin-bottom:.5rem;box-shadow:0 6px 16px #14213d44}.toast.error{background:#b91c1c}.toast.success{background:#166534}.confirm-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:#0000008a;padding:1rem}.confirm-modal.open{display:flex}.confirm-modal-box{width:min(96vw,420px);border-radius:1rem;background:#fff;padding:1.25rem}#toast-container{position:fixed;right:1rem;bottom:1rem;z-index:50}@media(max-width:1023px){.dashboard-shell aside{max-height:none}.dashboard-shell article{min-height:auto}.dashboard-shell{margin-bottom:2rem}.profile-grid,.profile-layout{grid-template-columns:1fr}.candidate-page{margin:0 -1.25rem}.profile-form{grid-template-columns:1fr}}</style></head><body class="bg-[#f8fafc] text-[#14213d]" data-csrf="' . self::e($_SESSION['csrf']) . '"><header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur"><div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4"><a href="./" class="display text-2xl font-bold text-[#17457c]">YEB<span class="text-[#fca311]">.</span></a><nav class="flex items-center gap-4">' . $nav . '</nav></div></header><main class="mx-auto max-w-6xl px-5 md:px-8" id="app-root" data-api-base="./?ajax=1&format=json">' . $content . '</main><div id="toast-container" aria-live="polite" aria-atomic="true"></div><div id="confirm-modal" class="confirm-modal" role="dialog" aria-modal="true" aria-hidden="true"><div class="confirm-modal-box"><h2 class="text-lg font-bold" id="confirm-title">Confirmar acción</h2><p class="mt-3 text-sm text-slate-600" id="confirm-message">¿Deseas continuar?</p><div class="mt-5 flex justify-end gap-2"><button type="button" class="filter-pill" data-confirm-cancel>Cancelar</button><button type="button" class="primary-button" data-confirm-accept>Confirmar</button></div></div></div><footer class="border-t bg-white"><div class="mx-auto max-w-6xl px-5 py-6 text-sm text-slate-500">YEB · Youth Employment Bridge · Conectando talento y oportunidades</div></footer><script type="module" src="public/js/app.js"></script></body></html>';
    }

    private static function alerts(?string $message, ?string $error): string
    {
        return ($error ? '<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">' . self::e($error) . '</div>' : '')
            . ($message ? '<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">' . self::e($message) . '</div>' : '');
    }

    private static function field(string $name, string $label, string $type = 'text'): string
    {
        return $type === 'textarea'
            ? '<label class="mt-4 block text-sm font-bold">' . $label . '<textarea required name="' . $name . '" class="mt-1 min-h-24 w-full rounded-lg border p-3"></textarea></label>'
            : '<label class="mt-4 block text-sm font-bold">' . $label . '<input required name="' . $name . '" type="' . $type . '" class="mt-1 w-full rounded-lg border p-3"></label>';
    }

    private static function fieldValue(string $name, string $label, ?string $value, string $type = 'text'): string
    {
        $value = self::e($value ?? '');
        if ($type === 'textarea') {
            return '<label class="text-sm font-bold md:col-span-2">' . $label . '<textarea name="' . $name . '" class="mt-1 min-h-24 w-full rounded-lg border p-3">' . $value . '</textarea></label>';
        }

        return '<label class="text-sm font-bold">' . $label . '<input name="' . $name . '" type="' . $type . '" value="' . $value . '" class="mt-1 w-full rounded-lg border p-3"></label>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
