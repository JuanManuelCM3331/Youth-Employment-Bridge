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
        return self::candidateDashboard($user, $message, $error, $searchJobs, $searchController);
    }

    private static function companyNav(string $active): string
    {
        $items = ['dashboard' => 'Resumen', 'company_profile' => 'Perfil de empresa', 'company_jobs' => 'Mis vacantes', 'company_applications' => 'Candidatos'];
        $html = '<nav class="profile-tabs company-tabs">';
        foreach ($items as $key => $label) $html .= '<a class="' . ($key === $active ? 'active' : '') . '" href="?action=' . $key . '">' . $label . '</a>';
        return $html . '</nav>';
    }

    public static function companyArea(array $user, string $action, ?string $message, ?string $error, CompanyProfileService $companies, JobsController $jobsController, ApplicationsController $applicationsController): string
    {
        $company = $companies->find($user['company_id']);
        if (!$company) return self::alerts($message, 'No se encontró la empresa asociada a la cuenta.');
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
        return '<article class="profile-card company-section"><div class="flex items-center justify-between"><h2>Información corporativa</h2><span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">' . self::iconCheck('w-3.5 h-3.5') . ' Empresa verificada</span></div><p class="section-copy">Mantén actualizados los datos que verán los candidatos.</p><form method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre de empresa', $company['name']) . self::fieldValue('city', 'Ciudad', $company['city']) . self::fieldValue('industry', 'Sector', $company['industry']) . self::fieldValue('size', 'Tamaño', $company['size']) . self::fieldValue('website', 'Sitio web', $company['website'], 'url') . self::fieldValue('phone', 'Teléfono', $company['phone']) . self::fieldValue('contact_email', 'Correo de contacto', $company['contact_email'], 'email') . self::fieldValue('description', 'Descripción', $company['description'], 'textarea') . '<label>Logo o foto corporativa<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><button class="primary-button">Guardar perfil corporativo</button></form></article>';
    }

    private static function companyJobsView(array $jobs, array $user): string
    {
        $rows = ''; foreach ($jobs as $job) { $closeBtn = $job['status'] === 'published' ? '<form method="post" action="?action=close_job" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="' . $job['id'] . '"><button class="text-xs font-semibold text-red-600 hover:text-red-700 border border-red-200 hover:bg-red-50 px-2.5 py-1 rounded-md transition" onclick="return confirm(\'¿Cerrar esta vacante?\')">Cerrar</button></form>' : ''; $statusConfig = match($job['status']) { 'published' => ['label' => 'Publicada', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'], 'draft' => ['label' => 'Borrador', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'], default => ['label' => 'Cerrada', 'class' => 'bg-slate-100 text-slate-600 border-slate-200'] }; $rows .= '<article class="application-card"><div><h2>' . self::e($job['title']) . '</h2><p>' . self::e($job['city']) . ' · ' . self::e($job['work_mode']) . '</p></div><div class="flex items-center gap-3"><span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span><span class="text-xs text-slate-500 font-medium">' . $job['applications_count'] . ' candidatos</span>' . $closeBtn . '</div></article>'; }
        return '<div class="company-columns" id="crear"><form method="post" action="?action=create_job" class="profile-card company-section"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><h2>Crear una vacante</h2><p class="section-copy">Publica oportunidades claras y atractivas para los candidatos.</p>' . self::field('title', 'Título') . self::field('description', 'Descripción', 'textarea') . self::field('city', 'Ciudad') . self::field('experience', 'Experiencia') . '<div class="grid grid-cols-2 gap-3">' . self::field('salary_min', 'Salario mínimo', 'number') . self::field('salary_max', 'Salario máximo', 'number') . '</div><label class="mt-4 block text-sm font-bold">Modalidad<select name="work_mode" class="mt-1 w-full rounded-lg border border-slate-200 p-3"><option value="hybrid">Híbrido</option><option value="remote">Remoto</option><option value="onsite">Presencial</option></select></label><button class="primary-button mt-5">Publicar vacante</button></form><article class="profile-card company-section"><h2>Mis vacantes</h2><div class="mt-5">' . ($rows ?: '<p class="text-slate-500">Aún no tienes vacantes.</p>') . '</div></article></div>';
    }

    private static function companyApplicationsView(array $applications, array $user): string
    {
        $rows = ''; foreach ($applications as $item) { $cv = $item['cv_original_name'] ? '<a class="profile-link font-semibold text-xs" href="?action=download_cv&candidate=' . $item['candidate_id'] . '">Descargar hoja de vida</a>' : '<span class="text-xs text-slate-400">Sin CV</span>'; $statusOptions = ''; foreach (['pending' => 'Pendiente', 'review' => 'En revisión', 'interview' => 'Entrevista', 'accepted' => 'Aceptado', 'rejected' => 'Rechazado'] as $val => $label) $statusOptions .= '<option value="' . $val . '"' . ($item['status'] === $val ? ' selected' : '') . '>' . $label . '</option>'; $rows .= '<article class="application-card"><div><h2>' . self::e($item['candidate_name']) . '</h2><p>' . self::e($item['email']) . ' · ' . self::e($item['title']) . '</p>' . $cv . '</div><form method="post" action="?action=update_application" class="flex items-center gap-2"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="application_id" value="' . $item['id'] . '"><select name="status" class="rounded-lg border border-slate-200 p-2 text-sm">' . $statusOptions . '</select><button class="primary-button !py-2 !px-3 text-xs">Actualizar</button></form></article>'; }
        return '<article class="profile-card company-section"><div class="flex items-center justify-between"><div><h2>Candidatos</h2><p class="section-copy">Revisa perfiles, descarga hojas de vida y actualiza cada proceso.</p></div><span class="company-count">' . count($applications) . ' postulaciones</span></div><div class="mt-5">' . ($rows ?: '<p class="text-slate-500">No hay postulaciones recibidas.</p>') . '</div></article>';
    }

    public static function candidateDashboard(array $user, ?string $message, ?string $error, array $searchJobs, SearchController $searchController): string
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
            $jobCards .= '<a href="?action=dashboard&q=' . rawurlencode($_GET['q'] ?? '') . '&location=' . rawurlencode($_GET['location'] ?? '') . '&job=' . $job['id'] . '" class="block border-b border-slate-200 p-5 transition ' . ($isSelected ? 'border-l-4 border-l-[#17457c] bg-white shadow-sm' : 'bg-white hover:bg-slate-50') . '"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wider text-[#c64a44]">Oportunidad</p><h3 class="mt-1 text-base font-bold text-[#25364b]">' . self::e($job['title']) . '</h3><p class="mt-1 text-sm font-medium text-slate-600">' . self::e($job['company_name']) . '</p></div><span class="p-1">' . self::iconHeart($isSaved, 'w-5 h-5') . '</span></div><p class="mt-2.5 text-xs text-slate-500">' . self::e($job['city']) . ' · ' . self::e($job['work_mode']) . '</p><div class="mt-3 flex items-center justify-between text-xs text-slate-400"><span>' . self::e($job['experience']) . '</span><span>' . date('d M', strtotime($job['created_at'])) . '</span></div></a>';
        }
        $detail = $selected ? '<div class="flex items-start justify-between gap-5"><div><p class="text-xs font-bold uppercase tracking-wider text-[#c64a44]">Oportunidad seleccionada</p><h1 class="mt-2 text-2xl lg:text-3xl font-bold text-[#25364b]">' . self::e($selected['title']) . '</h1><p class="mt-1.5 text-base font-semibold text-[#17457c]">' . self::e($selected['company_name']) . '</p><p class="mt-1 text-sm text-slate-500">' . self::e($selected['city']) . '</p></div><div class="hidden h-14 w-14 items-center justify-center rounded-xl bg-slate-100 sm:flex">' . self::iconBriefcase('w-7 h-7 text-slate-400') . '</div></div><div class="mt-6 flex flex-wrap items-center gap-3"><form method="post" action="?action=apply"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="' . $selected['id'] . '"><button class="rounded-full bg-[#17457c] px-7 py-2.5 font-bold text-white shadow-sm transition hover:bg-[#12365f]">Aplicar a la vacante</button></form><form method="post" action="?action=toggle_saved_job"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="return_to" value="' . self::e('?action=search&q=' . rawurlencode($_GET['q'] ?? '') . '&location=' . rawurlencode($_GET['location'] ?? '') . '&job=' . (string) $selected['id']) . '"><input type="hidden" name="job_id" value="' . $selected['id'] . '"><button class="circle-action" title="Guardar vacante">' . self::iconHeart(in_array((int) $selected['id'], $savedIds, true), 'w-5 h-5') . '</button></form><button class="circle-action" title="Copiar enlace" onclick="navigator.clipboard?.writeText(window.location.href);alert(\'Enlace copiado al portapapeles\');return false">' . self::iconShare('w-5 h-5 text-[#17457c]') . '</button><form method="post" action="?action=hide_job"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="return_to" value="' . self::e('?action=search&q=' . rawurlencode($_GET['q'] ?? '') . '&location=' . rawurlencode($_GET['location'] ?? '') . '&job=' . (string) $selected['id']) . '"><input type="hidden" name="job_id" value="' . $selected['id'] . '"><button class="circle-action" title="Ocultar vacante">' . self::iconEyeOff('w-5 h-5 text-slate-500') . '</button></form></div><div class="my-6 border-t border-slate-200"></div><div class="grid gap-4 text-sm text-slate-700 sm:grid-cols-3"><div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5"><div class="flex items-center gap-2 text-slate-500">' . self::iconBriefcase('w-4 h-4') . '<span class="text-xs">Modalidad</span></div><p class="mt-1 font-semibold text-slate-800">' . self::e($selected['work_mode']) . '</p></div><div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5"><div class="flex items-center gap-2 text-slate-500">' . self::iconClock('w-4 h-4') . '<span class="text-xs">Experiencia</span></div><p class="mt-1 font-semibold text-slate-800">' . self::e($selected['experience']) . '</p></div><div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5"><div class="flex items-center gap-2 text-slate-500">' . self::iconCurrency('w-4 h-4') . '<span class="text-xs">Rango Salarial</span></div><p class="mt-1 font-semibold text-slate-800">$' . number_format((float) $selected['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $selected['salary_max'], 0, ',', '.') . '</p></div></div><div class="mt-7 border-t border-slate-200 pt-6"><h2 class="text-lg font-bold text-[#25364b]">Descripción de la oferta</h2><p class="mt-3.5 whitespace-pre-line leading-7 text-slate-600 text-sm">' . self::e($selected['description']) . '</p></div>' : '<div class="flex h-full items-center justify-center p-12 text-center text-slate-400">No se encontraron vacantes con los criterios de búsqueda.</div>';
        return $html . '<section class="dashboard-shell -mx-5 min-h-[calc(100vh-78px)] bg-[#f4f7fb] md:-mx-8"><div class="border-b border-slate-200 bg-white px-5 py-4 md:px-8"><form method="get" class="mx-auto flex max-w-7xl flex-col gap-3 md:flex-row"><input type="hidden" name="action" value="dashboard"><div class="search-field flex-1 flex items-center gap-2.5 rounded-full border border-slate-200 bg-white px-4 py-2"><span class="text-slate-400">' . self::iconSearch('w-4 h-4') . '</span><input name="q" value="' . $keyword . '" placeholder="Cargo o palabra clave" class="w-full text-sm outline-none"></div><div class="search-field flex-1 flex items-center gap-2.5 rounded-full border border-slate-200 bg-white px-4 py-2"><span class="text-slate-400">' . self::iconLocation('w-4 h-4') . '</span><input name="location" value="' . $location . '" placeholder="Ciudad o modalidad" class="w-full text-sm outline-none"></div><button class="rounded-full bg-[#17457c] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#12365f]">Buscar empleos</button></form><div class="mx-auto mt-3 flex max-w-7xl gap-2 overflow-x-auto pb-1 text-xs"><a href="?action=dashboard" class="filter-pill">Recientes</a><a href="?action=dashboard&location=Remote" class="filter-pill">Remoto</a><a href="?action=dashboard&location=Hybrid" class="filter-pill">Híbrido</a><a href="?action=dashboard&location=Presencial" class="filter-pill">Presencial</a></div></div><div class="mx-auto grid max-w-7xl lg:grid-cols-[minmax(330px,38%)_1fr]"><aside class="max-h-[calc(100vh-210px)] overflow-y-auto border-r border-slate-200 bg-white"><div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3.5"><p class="text-sm font-semibold text-slate-700"><strong>' . count($jobs) . '</strong> ofertas disponibles</p></div>' . $jobCards . '</aside><article class="min-h-[calc(100vh-210px)] bg-white p-6 md:p-8">' . $detail . '</article></div></section>';
    }

    private static function candidateNav(string $active): string
    {
        $items = ['dashboard' => 'Mi área', 'profile' => 'Hoja de Vida', 'applications' => 'Mis aplicaciones', 'alerts' => 'Mis alertas', 'favorites' => 'Mis favoritos'];
        $html = '<nav class="profile-tabs">';
        foreach ($items as $key => $label) $html .= '<a class="' . ($key === $active ? 'active' : '') . '" href="?action=' . $key . '">' . $label . '</a>';
        return $html . '</nav>';
    }

    public static function candidateArea(array $user, ?string $message, ?string $error, array $jobs, UserController $userController, ApplicationsController $applicationsController): string
    {
        $profile = $userController->profile($user['id']);
        if (!$profile) return self::alerts($message, 'No se encontró el perfil del usuario.');
        $counts = array_fill_keys(['pending', 'review', 'interview', 'accepted', 'rejected'], 0);
        foreach ($applicationsController->countByStatus($user['id']) as $row) $counts[$row['status']] = (int) $row['total'];
        $completed = 30 + ($profile['cv_path'] ? 25 : 0) + ($profile['professional_title'] ? 15 : 0) + ($profile['skills'] ? 15 : 0) + ($profile['education'] ? 15 : 0);
        $recommendations = '';
        foreach (array_slice($jobs, 0, 4) as $job) $recommendations .= '<a href="?action=search&job=' . $job['id'] . '" class="profile-job"><p class="text-xs font-bold uppercase tracking-wider text-[#c64a44]">Oportunidad recomendada</p><h3>' . self::e($job['title']) . '</h3><p>' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p><span>$' . number_format((float) $job['salary_min'], 0, ',', '.') . '</span></a>';
        $photo = $profile['profile_photo_path'] ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto de perfil" class="profile-avatar">' : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('dashboard') . '<div class="profile-grid"><div><article class="profile-card"><div class="flex items-center gap-5">' . $photo . '<div><h1>' . self::e($profile['name']) . '</h1><p>' . self::e($profile['professional_title'] ?: 'Completa tu titular profesional') . '</p><p class="profile-completion">Perfil completado <strong>' . $completed . '%</strong></p></div><a class="ml-auto text-xl text-slate-400 hover:text-[#17457c]" href="?action=profile">→</a></div><div class="progress-track"><span style="width:' . $completed . '%"></span></div><div class="mt-6 flex justify-between text-sm text-slate-500"><span>Tu perfil profesional</span><a class="font-bold text-[#17457c]" href="?action=profile">Editar perfil</a></div></article><article class="profile-card mt-6"><h2>Mis aplicaciones</h2><div class="stats-grid"><div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-500"></span><span>Postulado</span><strong>' . ($counts['pending'] + $counts['review']) . '</strong></div><div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span><span>En proceso</span><strong>' . $counts['interview'] . '</strong></div><div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span><span>Finalista</span><strong>' . $counts['accepted'] . '</strong></div><div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-400"></span><span>Finalizadas</span><strong>' . $counts['rejected'] . '</strong></div></div><a href="?action=applications" class="profile-link">Ver mis aplicaciones →</a></article><article class="profile-card mt-6"><h2>Accesos rápidos</h2><div class="quick-links"><a href="?action=profile"><span>Editar hoja de vida</span> →</a><a href="?action=favorites"><span>Ofertas favoritas</span> →</a><a href="?action=alerts"><span>Configurar alertas</span> →</a><a href="?action=search"><span>Buscar empleos</span> →</a></div></article></div><div><article class="profile-card recommendations"><h2>Descubre estas <strong>ofertas que te pueden interesar</strong></h2>' . ($recommendations ?: '<p class="p-6 text-slate-500">Aún no hay recomendaciones disponibles.</p>') . '</article><article class="premium-card mt-6"><h2>Potencia tu búsqueda de empleo</h2><p>Completa tu perfil y mantén tu hoja de vida actualizada para destacar ante las empresas.</p><a href="?action=profile">Completar perfil</a></article></div></div></section>';
    }

    public static function candidateProfile(array $user, ?string $message, ?string $error, UserController $userController): string
    {
        $profile = $userController->profile($user['id']);
        if (!$profile) return self::alerts($message, 'No se encontró el perfil del usuario.');
        $cv = $profile['cv_original_name'] ? '<div class="flex items-center gap-2 text-emerald-700 font-medium text-sm">' . self::iconCheck('w-4 h-4') . '<span>' . self::e($profile['cv_original_name']) . '</span> · <a class="font-bold underline" href="?action=download_cv&candidate=' . $user['id'] . '">Descargar</a> · <form method="post" action="?action=delete_cv" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><button class="font-bold text-red-600 hover:underline" onclick="return confirm(\'¿Eliminar tu hoja de vida?\')">Eliminar</button></form></div>' : '<p class="text-slate-500 text-sm">No has cargado una hoja de vida.</p>';
        $photo = $profile['profile_photo_path'] ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto" class="profile-avatar">' : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';
        $form = '<form method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre completo', $profile['name']) . self::fieldValue('professional_title', 'Titular profesional', $profile['professional_title']) . self::fieldValue('phone', 'Teléfono', $profile['phone']) . self::fieldValue('location', 'Ubicación', $profile['location']) . self::fieldValue('availability', 'Disponibilidad', $profile['availability']) . self::fieldValue('linkedin_url', 'LinkedIn', $profile['linkedin_url'], 'url') . self::fieldValue('portfolio_url', 'Portafolio', $profile['portfolio_url'], 'url') . self::fieldValue('skills', 'Conocimientos y habilidades', $profile['skills'], 'textarea') . self::fieldValue('experience', 'Experiencia profesional', $profile['experience'], 'textarea') . self::fieldValue('education', 'Educación', $profile['education'], 'textarea') . self::fieldValue('bio', 'Resumen profesional', $profile['bio'], 'textarea') . '<label>Foto de perfil<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><button class="primary-button">Guardar cambios</button></form>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('profile') . '<h1 class="page-title">Hoja de Vida</h1><div class="profile-layout"><div><article class="profile-card profile-identity"><div class="flex items-center gap-5">' . $photo . '<div><h2>' . self::e($profile['name']) . '</h2><p>' . self::e($profile['location'] ?: 'Agrega tu ubicación') . '</p><p>' . self::e($profile['phone'] ?: 'Agrega tu teléfono') . '</p></div></div><div class="mt-8">' . $form . '</div></article><article class="profile-card mt-6"><h2>Documentos adjuntos</h2><div class="mt-4">' . $cv . '</div><form method="post" action="?action=upload_cv" enctype="multipart/form-data" class="mt-5 flex gap-3"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required type="file" name="cv" accept=".pdf,.doc,.docx" class="flex-1 rounded-lg border border-slate-200 p-2.5 text-sm"><button class="primary-button !py-2.5">Subir CV</button></form></article></div><aside class="profile-card side-card"><h2>Tu perfil profesional</h2><p class="mt-2 text-sm text-slate-500">Una información completa y detallada aumenta las posibilidades de ser contactado por reclutadores.</p><a href="?action=dashboard" class="mt-4 inline-block font-bold text-sm text-[#17457c]">Volver a Mi área →</a></aside></div></section>';
    }

    public static function candidateApplications(array $user, ?string $message, ?string $error, ApplicationsController $applicationsController): string
    {
        $rows = '';
        foreach ($applicationsController->byUser($user['id']) as $item) {
            $statusConfig = match($item['status']) {
                'accepted' => ['label' => 'Aceptado', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                'interview' => ['label' => 'Entrevista', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                'review' => ['label' => 'En revisión', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                'rejected' => ['label' => 'Rechazado', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
                default => ['label' => 'Pendiente', 'class' => 'bg-slate-50 text-slate-600 border-slate-200']
            };
            $rows .= '<article class="application-card"><div><h2>' . self::e($item['title']) . '</h2><p>' . self::e($item['company_name']) . ' · ' . self::e($item['city']) . '</p></div><span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span></article>';
        }
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('applications') . '<h1 class="page-title">Mis aplicaciones</h1><div class="application-list">' . ($rows ?: '<div class="profile-card p-8 text-center text-slate-500">Aún no tienes aplicaciones registradas.</div>') . '</div></section>';
    }

    public static function candidateFavorites(array $user, ?string $message, ?string $error, SearchController $searchController): string
    {
        $rows = '';
        foreach ($searchController->savedJobs($user['id']) as $job) {
            $rows .= '<a class="application-card hover:border-slate-300 transition" href="?action=search&job=' . $job['id'] . '"><div><h2>' . self::e($job['title']) . '</h2><p>' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p></div><span>' . self::iconHeart(true, 'w-5 h-5') . '</span></a>';
        }
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('favorites') . '<h1 class="page-title">Mis favoritos</h1><div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 mb-3">' . self::iconHeart(false, 'w-7 h-7 text-slate-400') . '</div><h2>Todavía no tienes ofertas guardadas</h2><p class="text-slate-500 text-sm mb-4">Guarda vacantes de tu interés para revisarlas más tarde.</p><a href="?action=search" class="primary-button">Buscar empleos</a></div>') . '</div></section>';
    }

    public static function candidateAlerts(array $user, ?string $message, ?string $error, NotificationsController $notificationsController): string
    {
        $items = $notificationsController->forUser($user['id']);
        $rows = '';
        foreach ($items as $item) {
            $isRead = !empty($item['read_at']);
            $markBtn = !$isRead ? '<form method="post" action="?action=mark_notification_read" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="notification_id" value="' . $item['id'] . '"><button class="text-xs font-semibold text-[#17457c] hover:underline">Marcar leída</button></form>' : '<span class="text-xs text-slate-400">Leída</span>';
            $rows .= '<article class="application-card' . ($isRead ? ' opacity-60' : '') . '"><div><h2>' . self::e($item['message']) . '</h2><p class="text-xs text-slate-400 mt-1">' . self::e($item['created_at']) . '</p></div>' . $markBtn . '</article>';
        }
        $markAllBtn = $items ? '<form method="post" action="?action=mark_all_notifications_read" class="mb-4 text-right"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><button class="text-sm font-semibold text-[#17457c] hover:underline">Marcar todas como leídas</button></form>' : '';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('alerts') . '<h1 class="page-title">Mis alertas</h1>' . $markAllBtn . '<div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 mb-3">' . self::iconBell('w-7 h-7 text-slate-400') . '</div><h2>No tienes alertas todavía</h2><p class="text-slate-500 text-sm">Recibirás notificaciones cuando haya cambios en tus postulaciones.</p></div>') . '</div></section>';
    }

    public static function home(array $jobs, ?array $user, string $action, ?string $message, ?string $error): string
    {
        $alerts = self::alerts($message, $error);
        if ($action === 'login' || $action === 'register' || $action === 'register_company') {
            return $alerts . '<section class="mx-auto max-w-md rounded-2xl border bg-white p-8 shadow-sm">' 
            . ($action === 'login' 
                ? '<h1 class="display mb-6 text-4xl font-bold">Ingresar</h1><form method="post" class="space-y-4"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3"><input required name="password" type="password" placeholder="Contraseña" class="w-full rounded-lg border p-3"><button class="w-full rounded-lg bg-blue-600 p-3 font-bold text-white">Continuar</button></form><p class="mt-4 text-center text-sm text-slate-500">¿No tienes cuenta? <a href="?action=register" class="font-bold text-[#17457c]">Crear perfil</a></p>'
                : ($action === 'register'
                    ? '<h1 class="display mb-6 text-4xl font-bold">Crear perfil</h1><div class="mb-6 flex gap-2"><a href="?action=register" class="flex-1 rounded-lg bg-[#17457c] p-2 text-center text-sm font-bold text-white">Candidato</a><a href="?action=register_company" class="flex-1 rounded-lg border p-2 text-center text-sm font-bold text-slate-600">Empresa</a></div><form method="post" class="space-y-4"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required name="name" placeholder="Nombre completo" class="w-full rounded-lg border p-3"><input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3"><input required name="password" type="password" placeholder="Contraseña" class="w-full rounded-lg border p-3"><button class="w-full rounded-lg bg-blue-600 p-3 font-bold text-white">Crear cuenta</button></form><p class="mt-4 text-center text-sm text-slate-500">¿Ya tienes cuenta? <a href="?action=login" class="font-bold text-[#17457c]">Ingresar</a></p>'
                    : '<h1 class="display mb-6 text-4xl font-bold">Registrar empresa</h1><div class="mb-6 flex gap-2"><a href="?action=register" class="flex-1 rounded-lg border p-2 text-center text-sm font-bold text-slate-600">Candidato</a><a href="?action=register_company" class="flex-1 rounded-lg bg-[#17457c] p-2 text-center text-sm font-bold text-white">Empresa</a></div><form method="post" class="space-y-4"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required name="name" placeholder="Tu nombre completo" class="w-full rounded-lg border p-3"><input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3"><input required name="password" type="password" placeholder="Contraseña" class="w-full rounded-lg border p-3"><input required name="company_name" placeholder="Nombre de la empresa" class="w-full rounded-lg border p-3"><input required name="city" placeholder="Ciudad" class="w-full rounded-lg border p-3"><button class="w-full rounded-lg bg-blue-600 p-3 font-bold text-white">Registrar empresa</button></form><p class="mt-4 text-center text-sm text-slate-500">¿Ya tienes cuenta? <a href="?action=login" class="font-bold text-[#17457c]">Ingresar</a></p>'
                ))
            . '</section>';
        }
        $cards = '';
        foreach ($jobs as $job) $cards .= '<article class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md"><span class="rounded-full bg-blue-50 border border-blue-100 px-3 py-1 text-xs font-semibold text-[#17457c]">' . self::e($job['work_mode']) . '</span><h3 class="mt-4 text-xl font-bold"><a href="?action=job_detail&id=' . $job['id'] . '" class="hover:text-[#17457c] transition">' . self::e($job['title']) . '</a></h3><p class="mt-1 text-sm font-medium text-slate-500">' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p><p class="mt-3.5 text-sm leading-6 text-slate-600 line-clamp-3">' . self::e($job['description']) . '</p><div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4"><span class="text-sm font-bold text-slate-800">$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.') . '</span>' . ($user && $user['role'] === 'candidate' ? '<form method="post" action="?action=apply" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="' . $job['id'] . '"><button class="font-bold text-sm text-[#17457c] hover:underline">Postularme →</button></form>' : '<a href="?action=login" class="font-bold text-sm text-[#17457c] hover:underline">Ingresar →</a>') . '</div></article>';
        return $alerts . '<section class="landing-hero relative -mx-5 overflow-hidden px-5 py-16 text-white md:-mx-8 md:px-8 md:py-24"><div class="absolute inset-0 bg-[#102e50]/60"></div><div class="relative mx-auto max-w-6xl"><div class="max-w-3xl"><p class="mb-4 text-xs font-bold uppercase tracking-[.25em] text-[#fca311]">Youth Employment Bridge</p><h1 class="display text-4xl font-bold leading-[1.1] md:text-6xl">El próximo paso de tu carrera profesional empieza aquí.</h1><p class="mt-5 max-w-2xl text-base leading-7 text-white/90 md:text-lg">Conecta con oportunidades laborales de calidad, postúlate en segundos y recibe seguimiento continuo.</p></div><form class="relative z-10 mt-9 grid gap-3 rounded-2xl bg-white p-3 shadow-2xl md:grid-cols-[1fr_1fr_auto]" method="get"><div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4"><span class="text-slate-400">' . self::iconSearch('w-5 h-5') . '</span><input name="q" value="' . self::e($_GET['q'] ?? '') . '" placeholder="Cargo, habilidad o empresa" class="w-full py-3 text-[#14213d] outline-none text-sm"></div><div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4"><span class="text-slate-400">' . self::iconLocation('w-5 h-5') . '</span><input name="location" value="' . self::e($_GET['location'] ?? '') . '" placeholder="Ciudad o modalidad" class="w-full py-3 text-[#14213d] outline-none text-sm"></div><button class="rounded-xl bg-[#17457c] px-7 py-3 font-bold text-white transition hover:bg-[#12365f]">Buscar empleos</button></form></div></section><section class="mx-auto max-w-6xl py-12"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-[#17457c]">Explora oportunidades</p><h2 class="display mt-1 text-3xl font-bold">Vacantes recientes</h2></div><span class="text-sm font-medium text-slate-500">' . count($jobs) . ' resultados disponibles</span></div><div class="mt-7 grid gap-6 md:grid-cols-2 lg:grid-cols-3">' . $cards . '</div></section><section class="border-t border-slate-200 py-12"><div class="grid gap-6 md:grid-cols-3"><a href="?action=register" class="rounded-2xl bg-[#14213d] p-6 text-white transition hover:-translate-y-1 shadow-sm"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 mb-4">' . self::iconUser('w-6 h-6 text-white') . '</div><h3 class="text-lg font-bold">Crea tu perfil</h3><p class="mt-1.5 text-sm text-white/75 leading-relaxed">Presenta tu experiencia y conecta con empresas que buscan talento joven.</p></a><a href="?action=login" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-md"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 mb-4">' . self::iconDocument('w-6 h-6 text-[#fca311]') . '</div><h3 class="text-lg font-bold">Sigue tus postulaciones</h3><p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Consulta el estado de cada proceso de selección en tiempo real.</p></a><a href="?action=register_company" class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-md"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 mb-4">' . self::iconBriefcase('w-6 h-6 text-[#17457c]') . '</div><h3 class="text-lg font-bold">Publica como empresa</h3><p class="mt-1.5 text-sm text-slate-500 leading-relaxed">Encuentra talento capacitado y gestiona vacantes de forma eficiente.</p></a></div></section>';
    }

    public static function jobDetail(?array $job, ?array $user): string
    {
        if (!$job) return '<div class="py-16 text-center"><h1 class="text-2xl font-bold text-slate-700">Vacante no encontrada</h1><p class="mt-2 text-slate-500">Esta vacante ya no está disponible.</p><a href="./" class="mt-4 inline-block font-bold text-[#17457c]">Volver al inicio →</a></div>';
        $salary = '$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.');
        $applyBtn = ($user && $user['role'] === 'candidate')
            ? '<form method="post" action="?action=apply"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="job_id" value="' . $job['id'] . '"><button class="rounded-full bg-[#17457c] px-8 py-3 font-bold text-white shadow-sm transition hover:bg-[#12365f]">Aplicar ahora</button></form>'
            : '<a href="?action=login" class="rounded-full bg-[#17457c] px-8 py-3 font-bold text-white">Ingresar para aplicar</a>';
        return '<section class="mx-auto max-w-3xl py-10"><a href="./" class="text-sm font-bold text-[#17457c]">← Volver a ofertas</a><div class="mt-6 rounded-2xl border bg-white p-8 shadow-sm"><p class="text-sm font-bold uppercase tracking-wide text-[#c64a44]">Oportunidad laboral</p><h1 class="mt-3 text-3xl font-bold text-[#25364b]">' . self::e($job['title']) . '</h1><p class="mt-2 text-lg font-semibold text-slate-600">' . self::e($job['company_name'] ?? 'Empresa') . '</p><p class="mt-1 text-slate-500">' . self::e($job['city']) . '</p><div class="mt-6 flex flex-wrap gap-3">' . $applyBtn . '</div><div class="my-7 border-t border-slate-200"></div><div class="grid gap-4 text-sm text-slate-700 sm:grid-cols-3"><div><p class="font-semibold">' . self::e($job['work_mode']) . '</p><span class="text-slate-500">Modalidad</span></div><div><p class="font-semibold">' . self::e($job['experience']) . '</p><span class="text-slate-500">Experiencia</span></div><div><p class="font-semibold">' . $salary . '</p><span class="text-slate-500">Salario</span></div></div><div class="mt-8 border-t border-slate-200 pt-7"><h2 class="text-xl font-bold text-[#25364b]">Descripción de la oferta</h2><p class="mt-4 whitespace-pre-line leading-7 text-slate-700">' . self::e($job['description']) . '</p></div></div></section>';
    }

    public static function layout(string $content, ?array $user, int $notificationCount = 0): string
    {
        $navLinks = [];
        $desktopLinksHtml = '';
        $mobileLinksHtml = '';

        if ($user) {
            $currentAction = $_GET['action'] ?? 'dashboard';
            if ($user['role'] === 'candidate') {
                $navLinks = [
                    ['action' => 'dashboard', 'label' => 'Mi área', 'href' => '?action=dashboard'],
                    ['action' => 'search', 'label' => 'Buscar empleos', 'href' => '?action=search'],
                    ['action' => 'applications', 'label' => 'Mis aplicaciones', 'href' => '?action=applications'],
                    ['action' => 'profile', 'label' => 'Hoja de Vida', 'href' => '?action=profile'],
                    ['action' => 'favorites', 'label' => 'Favoritos', 'href' => '?action=favorites'],
                ];
            } elseif ($user['role'] === 'recruiter') {
                $navLinks = [
                    ['action' => 'dashboard', 'label' => 'Resumen', 'href' => '?action=dashboard'],
                    ['action' => 'company_jobs', 'label' => 'Mis vacantes', 'href' => '?action=company_jobs'],
                    ['action' => 'company_applications', 'label' => 'Candidatos', 'href' => '?action=company_applications'],
                    ['action' => 'company_profile', 'label' => 'Perfil empresa', 'href' => '?action=company_profile'],
                    ['action' => 'home', 'label' => 'Ver portal', 'href' => './'],
                ];
            } elseif ($user['role'] === 'admin') {
                $navLinks = [
                    ['action' => 'dashboard', 'label' => 'Auditoría', 'href' => '?action=dashboard'],
                    ['action' => 'home', 'label' => 'Ver portal', 'href' => './'],
                ];
            }

            if (!empty($navLinks)) {
                $desktopLinksHtml = '<nav class="hidden md:flex items-center gap-1.5 lg:gap-2.5">';
                $mobileLinksHtml = '<div class="flex md:hidden items-center gap-2 overflow-x-auto py-2 border-t border-slate-100 text-xs font-semibold text-slate-600">';
                foreach ($navLinks as $item) {
                    $isActive = ($currentAction === $item['action']) || ($item['action'] === 'home' && empty($_GET['action']));
                    $deskClass = $isActive 
                        ? 'rounded-full bg-[#17457c]/10 text-[#17457c] font-bold px-3 py-1.5' 
                        : 'rounded-full text-slate-600 hover:text-[#17457c] hover:bg-slate-100 font-medium px-3 py-1.5 transition';
                    $mobClass = $isActive 
                        ? 'whitespace-nowrap rounded-lg bg-[#17457c] text-white px-2.5 py-1' 
                        : 'whitespace-nowrap rounded-lg bg-slate-100 text-slate-700 px-2.5 py-1';
                    
                    $desktopLinksHtml .= '<a href="' . $item['href'] . '" class="' . $deskClass . ' text-sm">' . self::e($item['label']) . '</a>';
                    $mobileLinksHtml .= '<a href="' . $item['href'] . '" class="' . $mobClass . '">' . self::e($item['label']) . '</a>';
                }
                $desktopLinksHtml .= '</nav>';
                $mobileLinksHtml .= '</div>';
            }

            $roleLabel = match($user['role']) {
                'recruiter' => 'Empresa',
                'admin' => 'Admin',
                default => 'Candidato'
            };

            $rightNav = '<div class="flex items-center gap-3">
                <a href="?action=alerts" class="relative flex items-center justify-center h-9 w-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition" title="Notificaciones">
                    ' . self::iconBell('w-5 h-5 text-slate-600') . 
                    ($notificationCount > 0 ? '<span class="absolute -top-1 -right-1 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white leading-none shadow-sm">' . $notificationCount . '</span>' : '') . '
                </a>
                <div class="hidden sm:flex flex-col text-right leading-tight">
                    <span class="font-bold text-sm text-[#25364b] max-w-[130px] truncate">' . self::e($user['name']) . '</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#17457c]">' . $roleLabel . '</span>
                </div>
                <form method="post" action="?action=logout" class="inline">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <button class="rounded-full border border-slate-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200 px-3.5 py-1.5 text-xs font-semibold text-slate-600 transition shadow-sm">Salir</button>
                </form>
            </div>';
        } else {
            $rightNav = '<div class="flex items-center gap-4">
                <a href="./#vacantes" class="hidden text-sm font-medium text-slate-600 hover:text-[#17457c] md:inline">Buscar ofertas</a>
                <a href="?action=login" class="text-sm font-medium text-slate-700 hover:text-[#17457c]">Ingresar</a>
                <a href="?action=register" class="rounded-full bg-[#17457c] px-5 py-2 text-sm font-bold text-white transition hover:bg-[#12365f] shadow-sm">Crear perfil</a>
            </div>';
        }

        $headerHtml = '<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-3 md:py-3.5 gap-4">
                <div class="flex items-center gap-6">
                    <a href="./" class="display text-2xl font-bold text-[#17457c]">YEB<span class="text-[#fca311]">.</span></a>
                    ' . $desktopLinksHtml . '
                </div>
                ' . $rightNav . '
            </div>
            ' . ($mobileLinksHtml ? '<div class="px-5 pb-2 max-w-6xl mx-auto">' . $mobileLinksHtml . '</div>' : '') . '
        </header>';

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>YEB | Youth Employment Bridge</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"><style>body{font-family:DM Sans,sans-serif}.display{font-family:Fraunces,serif}.landing-hero{background-image:url("https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=2200&q=85");background-position:center;background-size:cover}.filter-pill{white-space:nowrap;border:1px solid #e2e8f0;border-radius:.6rem;background:#fff;padding:.45rem .85rem;font-size:.8rem;font-weight:600;color:#475569;transition:all .15s}.filter-pill:hover{border-color:#cbd5e1;background:#f8fafc}.circle-action{display:inline-flex;height:2.75rem;width:2.75rem;align-items:center;justify-content:center;border-radius:999px;background:#f1f5f9;color:#334155;transition:all .2s;border:1px solid #e2e8f0}.circle-action:hover{background:#e2e8f0;color:#0f172a}.candidate-page{min-height:calc(100vh - 90px);background:#f8fafc;padding:1.5rem max(1rem,4vw) 4rem;margin:0 -2rem}.profile-tabs{display:flex;gap:.35rem;overflow:auto;border-radius:.9rem;background:#eef2f6;padding:.3rem;margin-bottom:2rem}.profile-tabs a{white-space:nowrap;border-radius:.7rem;padding:.65rem 1.25rem;color:#64748b;font-size:.875rem;font-weight:600;transition:all .15s}.profile-tabs a:hover{color:#1e293b}.profile-tabs a.active{background:#17457c;color:#fff;box-shadow:0 2px 8px rgba(23,69,124,.25)}.profile-grid{display:grid;grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr);gap:2rem;max-width:1180px;margin:auto}.profile-card,.premium-card{border-radius:1rem;background:#fff;padding:1.6rem;border:1px solid #e2e8f0;box-shadow:0 1px 3px 0 rgba(0,0,0,.04)}.profile-card h1{font-size:1.35rem;font-weight:700;color:#1e293b}.profile-card h2{font-size:1.2rem;font-weight:700;color:#1e293b}.profile-card p{color:#64748b}.profile-avatar{height:5.5rem;width:5.5rem;border-radius:999px;object-fit:cover;border:3px solid #e2e8f0;box-shadow:0 2px 6px rgba(0,0,0,.06)}.profile-avatar-empty{display:flex;align-items:center;justify-content:center;background:#17457c;color:#fff;font-size:1.8rem;height:5.5rem;width:5.5rem;border-radius:999px}.profile-completion{margin-top:.4rem;font-size:.85rem}.profile-completion strong{color:#059669}.progress-track{height:.45rem;border-radius:999px;background:#e2e8f0;margin-top:1.2rem;overflow:hidden}.progress-track span{display:block;height:100%;border-radius:inherit;background:#10b981}.stats-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-top:1rem}.stats-grid div{display:flex;align-items:center;gap:.6rem;font-size:.85rem;color:#475569}.stats-grid strong{margin-left:auto;color:#0f172a;font-size:1rem}.profile-link{display:inline-block;margin-top:1.2rem;color:#17457c;font-weight:600;font-size:.9rem}.profile-link:hover{text-decoration:underline}.quick-links{display:grid;margin-top:.75rem}.quick-links a{display:flex;align-items:center;justify-content:space-between;border-top:1px solid #f1f5f9;padding:.85rem 0;color:#334155;font-size:.925rem;font-weight:500;transition:color .15s}.quick-links a:hover{color:#17457c}.recommendations{padding:0;overflow:hidden}.recommendations h2{padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9;font-size:1.1rem;font-weight:600;color:#1e293b}.recommendations h2 strong{color:#17457c}.profile-job{display:block;border-bottom:1px solid #f1f5f9;padding:1.25rem 1.5rem;transition:background .15s}.profile-job:hover{background:#f8fafc}.profile-job h3{margin-top:.35rem;font-size:1rem;font-weight:700;color:#1e293b}.profile-job p{margin-top:.2rem;font-size:.85rem}.profile-job span{display:block;margin-top:.5rem;font-size:.85rem;font-weight:700;color:#17457c}.premium-card{background:linear-gradient(135deg,#17457c,#0f2d52);color:#fff;text-align:center;border:0}.premium-card h2{color:#fff}.premium-card p{margin:1rem 0;color:#cbd5e1;font-size:.925rem}.premium-card a,.primary-button{display:inline-block;border-radius:999px;background:#17457c;padding:.75rem 1.5rem;color:#fff;font-weight:600;font-size:.875rem;transition:all .15s;border:0}.premium-card a{background:#fff;color:#17457c}.premium-card a:hover{background:#f8fafc}.primary-button:hover{background:#12365f}.profile-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(250px,.5fr);gap:2rem;max-width:1180px;margin:auto}.page-title{max-width:1180px;margin:0 auto 1.25rem;font-size:1.85rem;font-weight:700;color:#1e293b}.profile-form{display:grid;grid-template-columns:repeat(2,1fr);gap:1.1rem;margin-top:1.5rem}.profile-form label{display:block;color:#334155;font-size:.85rem;font-weight:600}.profile-form label:nth-last-of-type(3),.profile-form label:nth-last-of-type(2),.profile-form label:nth-last-of-type(1){grid-column:1/-1}.profile-form input,.profile-form textarea{display:block;width:100%;margin-top:.35rem;border:1px solid #cbd5e1;border-radius:.6rem;padding:.65rem .85rem;font-size:.9rem;outline:none;transition:border-color .15s,box-shadow .15s}.profile-form input:focus,.profile-form textarea:focus{border-color:#17457c;box-shadow:0 0 0 3px rgba(23,69,124,.12)}.profile-form textarea{min-height:5.5rem}.application-list{max-width:900px;margin:auto}.application-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.85rem;border-radius:.9rem;background:#fff;padding:1.25rem 1.5rem;border:1px solid #e2e8f0;box-shadow:0 1px 2px 0 rgba(0,0,0,.03);transition:all .15s}.application-card:hover{border-color:#cbd5e1}.application-card h2{font-size:1rem;font-weight:700;color:#1e293b}.application-card p{margin-top:.25rem;color:#64748b;font-size:.85rem}.empty-state{text-align:center;padding:3.5rem 1.5rem}.empty-state h2{margin:1rem 0 .5rem;font-size:1.25rem;color:#1e293b}.side-card{align-self:start}.company-logo{height:4.5rem;width:4.5rem;border-radius:.75rem;object-fit:cover;border:1px solid #e2e8f0}.company-logo-empty{height:4.5rem;width:4.5rem;border-radius:.75rem;display:flex;align-items:center;justify-content:center;background:#17457c;color:#fff;font-size:1.5rem;font-weight:700}.company-heading{display:flex;align-items:center;gap:1.25rem;margin-bottom:1.75rem}.company-heading h1{font-size:1.75rem;font-weight:700;color:#1e293b}.company-heading p{color:#64748b;font-size:.9rem}.company-heading .eyebrow{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#17457c}.company-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.75rem}.company-metrics div{background:#fff;border:1px solid #e2e8f0;border-radius:.9rem;padding:1.25rem;box-shadow:0 1px 2px rgba(0,0,0,.03)}.company-metrics span{display:block;font-size:.8rem;color:#64748b;font-weight:500}.company-metrics strong{display:block;margin-top:.35rem;font-size:1.6rem;color:#1e293b}.company-columns{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}@media(max-width:1023px){.dashboard-shell aside{max-height:none}.dashboard-shell article{min-height:auto}.dashboard-shell{margin-bottom:2rem}.profile-grid,.profile-layout,.company-columns{grid-template-columns:1fr}.candidate-page{margin:0 -1.25rem}.profile-form{grid-template-columns:1fr}}</style></head><body class="bg-[#f8fafc] text-[#14213d]">' . $headerHtml . '<main class="mx-auto max-w-6xl px-5 md:px-8">' . $content . '</main><footer class="border-t bg-white mt-12"><div class="mx-auto max-w-6xl px-5 py-6 text-sm text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2"><span>YEB · Youth Employment Bridge</span><span>Conectando talento joven con oportunidades laborales</span></div></footer></body></html>';
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

    private static function iconSearch(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>';
    }

    private static function iconLocation(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>';
    }

    private static function iconBell(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>';
    }

    private static function iconHeart(bool $filled = false, string $class = 'w-5 h-5'): string
    {
        return $filled 
            ? '<svg class="' . $class . ' text-rose-500 fill-current inline-block" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>'
            : '<svg class="' . $class . ' text-slate-400 hover:text-rose-500 transition inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>';
    }

    private static function iconBriefcase(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
    }

    private static function iconClock(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    }

    private static function iconCurrency(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    }

    private static function iconShare(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>';
    }

    private static function iconEyeOff(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>';
    }

    private static function iconCheck(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
    }

    private static function iconUser(string $class = 'w-6 h-6'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';
    }

    private static function iconDocument(string $class = 'w-6 h-6'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
    }

}
