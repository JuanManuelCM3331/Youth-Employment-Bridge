<?php

namespace Modules\Companies\UI;

use App\Http\PortalViewRenderer;
use Modules\Applications\Controllers\ApplicationsController;
use Modules\Applications\UI\ApplicationsUI;
use Modules\Chat\Controllers\ChatController;
use Modules\Chat\UI\ChatUI;
use Modules\Companies\Services\CompanyProfileService;
use Modules\Jobs\Controllers\JobsController;
use Modules\Jobs\UI\JobsUI;

final class CompaniesUI extends PortalViewRenderer
{
    public static function adminCompaniesView(array $companies): string
    {

        $rows = '';

        foreach ($companies as $c) {

            $dateFormatted = date('Y-m-d', strtotime($c['created_at']));



            $actionBtn = '

            <form method="post" action="?action=admin_delete_company" class="inline" onsubmit="return confirm(\'¿Estás seguro de eliminar la empresa \\\' ' . addslashes(self::e($c['name'])) . ' \\\'? Esta acción eliminará todas las vacantes asociadas publicadas por esta empresa.\')">

                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                <input type="hidden" name="company_id" value="' . $c['id'] . '">

                <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 hover:border-rose-300 transition shadow-sm">

                    ' . self::iconTrash('w-3.5 h-3.5') . ' Eliminar

                </button>

            </form>';



            $rows .= '

            <tr class="company-row border-t border-slate-100 hover:bg-slate-50/70 transition">

                <td class="px-4 py-3.5 text-xs text-slate-400 font-mono">#' . $c['id'] . '</td>

                <td class="px-4 py-3.5">

                    <div class="flex items-center gap-3">

                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white font-extrabold text-xs">' . strtoupper(substr($c['name'], 0, 1)) . '</div>

                        <div>

                            <p class="font-bold text-sm text-slate-900">' . self::e($c['name']) . '</p>

                            <p class="text-xs text-slate-500 flex items-center gap-1">' . self::iconLocation('w-3 h-3 text-slate-400') . ' ' . self::e($c['city']) . '</p>

                        </div>

                    </div>

                </td>

                <td class="px-4 py-3.5 text-xs font-medium text-slate-700">' . self::e($c['industry'] ?: 'Reclutamiento') . '</td>

                <td class="px-4 py-3.5 text-xs text-slate-600">

                    <p>' . self::e($c['contact_email'] ?: 'Sin correo') . '</p>

                    <p class="text-[11px] text-slate-400">' . self::e($c['phone'] ?: '') . '</p>

                </td>

                <td class="px-4 py-3.5 text-xs">

                    <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">

                        ' . self::iconBriefcase('w-3.5 h-3.5') . ' ' . (int) ($c['jobs_count'] ?? 0) . '

                    </span>

                </td>

                <td class="px-4 py-3.5 text-xs">

                    <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 border border-blue-200">

                        ' . self::iconUser('w-3.5 h-3.5') . ' ' . (int) ($c['recruiters_count'] ?? 0) . '

                    </span>

                </td>

                <td class="px-4 py-3.5 text-xs text-slate-500 font-mono">' . $dateFormatted . '</td>

                <td class="px-4 py-3.5 text-right whitespace-nowrap">' . $actionBtn . '</td>

            </tr>';

        }



        if (!$rows) {

            $rows = '<tr><td colspan="8" class="p-12 text-center text-sm text-slate-400">No hay empresas registradas todavía.</td></tr>';

        }



        return '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-slate-100">

                <div>

                    <h2 class="text-2xl font-bold text-slate-900">Gestión de Empresas</h2>

                    <p class="text-xs text-slate-500 mt-1">Supervisa las organizaciones dadas de alta, sus vacantes activas y el personal de reclutamiento.</p>

                </div>

                <div class="flex items-center gap-3">

                    <div class="relative w-full sm:w-72">

                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>

                        <input id="companySearchInput" onkeyup="filterCompanyTable()" type="text" placeholder="Buscar por nombre, sector o ciudad..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">

                    </div>

                    <span class="whitespace-nowrap rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700">' . count($companies) . ' empresas</span>

                </div>

            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100">

                <table id="companyTable" class="w-full min-w-[900px] text-left text-sm">

                    <thead class="bg-slate-50/80 border-b border-slate-200/80">

                        <tr class="text-xs font-bold uppercase tracking-wider text-slate-500">

                            <th class="px-4 py-3">ID</th>

                            <th class="px-4 py-3">Empresa</th>

                            <th class="px-4 py-3">Sector</th>

                            <th class="px-4 py-3">Contacto</th>

                            <th class="px-4 py-3">Vacantes</th>

                            <th class="px-4 py-3">Reclutadores</th>

                            <th class="px-4 py-3">Fecha Alta</th>

                            <th class="px-4 py-3 text-right">Acción</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">' . $rows . '</tbody>

                </table>

            </div>

        </div>

        <script>

        function filterCompanyTable() {

            var input = document.getElementById("companySearchInput");

            var filter = input.value.toLowerCase();

            var rows = document.querySelectorAll(".company-row");

            rows.forEach(function(row) {

                var text = row.textContent.toLowerCase();

                row.style.display = text.indexOf(filter) > -1 ? "" : "none";

            });

        }

        </script>';

    }


    public static function companyNav(string $active): string
    {

        $items = [

            'dashboard' => ['label' => 'Resumen', 'icon' => self::iconChart('w-4 h-4')],

            'company_jobs' => ['label' => 'Mis vacantes', 'icon' => self::iconBriefcase('w-4 h-4')],

            'company_applications' => ['label' => 'Candidatos', 'icon' => self::iconUser('w-4 h-4')],

            'company_messages' => ['label' => 'Mensajes', 'icon' => self::iconChat('w-4 h-4')],

            'company_profile' => ['label' => 'Perfil corporativo', 'icon' => self::iconBuilding('w-4 h-4')],

            'alerts' => ['label' => 'Mis alertas', 'icon' => self::iconBell('w-4 h-4')],

        ];

        $html = '<nav class="profile-tabs company-tabs flex flex-wrap gap-2 p-1.5 rounded-2xl bg-slate-100 mb-8 border border-slate-200/80">';

        foreach ($items as $key => $item) {

            $isActive = ($key === $active);

            $class = $isActive

                ? 'inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition'

                : 'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-[#17457c] hover:text-white transition';

            $html .= '<a class="' . $class . '" href="?action=' . $key . '">' . $item['icon'] . '<span>' . $item['label'] . '</span></a>';

        }

        $html .= '</nav>';

        return $html;

    }


    public static function companyArea(array $user, string $action, ?string $message, ?string $error, CompanyProfileService $companies, JobsController $jobsController, ApplicationsController $applicationsController, ?ChatController $chatController = null): string
    {

        $companyId = (int) ($user['company_id'] ?? 0);

        $company = $companyId > 0 ? $companies->find($companyId) : null;

        if (!$company) {

            return self::alerts($message, $companyId > 0

                ? 'No se encontró la empresa asociada a la cuenta.'

                : 'Tu cuenta no está asociada a ninguna empresa. Contacta al administrador para que te vinculen con una.');

        }

        $jobList = $jobsController->byCompany($companyId);

        $applicationList = $applicationsController->byCompany($companyId);

        $profilePhoto = $company['profile_photo_path']

            ? '<img src="?action=download_profile_photo&type=company&id=' . $companyId . '" alt="Logo" class="company-logo h-20 w-20 rounded-2xl object-cover border-2 border-slate-200 shadow-sm">'

            : '<div class="company-logo company-logo-empty h-20 w-20 rounded-2xl flex items-center justify-center bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white font-extrabold text-2xl shadow-sm border border-blue-900/20">' . strtoupper(substr($company['name'], 0, 1)) . '</div>';



        $headerBanner = '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">

            <div class="flex items-center gap-5">

                ' . $profilePhoto . '

                <div>

                    <div class="flex flex-wrap items-center gap-2 mb-1.5">

                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200">

                            ' . self::iconBuilding('w-3.5 h-3.5') . ' Panel Corporativo

                        </span>

                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">

                            ' . self::iconCheck('w-3.5 h-3.5') . ' Empresa Verificada

                        </span>

                    </div>

                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">' . self::e($company['name']) . '</h1>

                    <p class="text-sm text-slate-500 mt-1 flex flex-wrap items-center gap-2">

                        <span class="font-medium text-slate-700">' . self::e($company['industry'] ?: 'Empresa Reclutadora') . '</span>

                        <span class="text-slate-300">·</span>

                        <span class="inline-flex items-center gap-1">' . self::iconLocation('w-3.5 h-3.5 text-slate-400') . ' ' . self::e($company['city']) . '</span>

                    </p>

                </div>

            </div>

            <div class="flex items-center gap-3">

                <a href="?action=company_jobs#crear" class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#12365f]">

                    ' . self::iconPlus('w-4 h-4') . ' Publicar vacante

                </a>

            </div>

        </div>';



        $content = self::alerts($message, $error) . '<section class="candidate-page company-page">' . self::companyNav($action) . $headerBanner;



        if ($action === 'company_profile') {

            $content .= self::companyProfileView($company, $user);

        } elseif ($action === 'company_jobs') {

            $content .= JobsUI::companyJobsView($jobList, $user);

        } elseif ($action === 'company_applications') {

            $content .= ApplicationsUI::companyApplicationsView($applicationList, $user);

        } elseif ($action === 'company_messages') {

            $conversations = $chatController ? $chatController->conversations($user) : [];

            $content .= ChatUI::companyMessagesView($conversations, $user);

        } else {

            $published = count(array_filter($jobList, fn(array $job): bool => $job['status'] === 'published'));

            $pending = count(array_filter($applicationList, fn(array $item): bool => in_array($item['status'], ['pending', 'review'], true)));



            $cards = '

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                    <div class="flex items-center justify-between">

                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Vacantes Activas</span>

                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">' . self::iconBriefcase('w-5 h-5') . '</div>

                    </div>

                    <p class="mt-3 text-3xl font-extrabold text-slate-900">' . $published . '</p>

                    <span class="mt-1 block text-xs font-semibold text-emerald-600">Publicadas en el portal</span>

                </div>

                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                    <div class="flex items-center justify-between">

                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total de Vacantes</span>

                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">' . self::iconDocument('w-5 h-5') . '</div>

                    </div>

                    <p class="mt-3 text-3xl font-extrabold text-slate-900">' . count($jobList) . '</p>

                    <span class="mt-1 block text-xs font-medium text-slate-500">Historial completo</span>

                </div>

                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                    <div class="flex items-center justify-between">

                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Por Evaluar</span>

                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">' . self::iconClock('w-5 h-5') . '</div>

                    </div>

                    <p class="mt-3 text-3xl font-extrabold text-slate-900">' . $pending . '</p>

                    <span class="mt-1 block text-xs font-semibold text-amber-600">Candidatos pendientes</span>

                </div>

                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                    <div class="flex items-center justify-between">

                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Postulaciones</span>

                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-600">' . self::iconUser('w-5 h-5') . '</div>

                    </div>

                    <p class="mt-3 text-3xl font-extrabold text-slate-900">' . count($applicationList) . '</p>

                    <span class="mt-1 block text-xs font-medium text-slate-500">Postulaciones recibidas</span>

                </div>

            </div>';



            $recent = '';

            foreach (array_slice($applicationList, 0, 5) as $item) {

                $statusConfig = match ($item['status']) {

                    'accepted' => ['label' => 'Aceptado', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],

                    'interview' => ['label' => 'Entrevista', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],

                    'review' => ['label' => 'En revisión', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],

                    'rejected' => ['label' => 'Rechazado', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],

                    default => ['label' => 'Pendiente', 'class' => 'bg-slate-50 text-slate-600 border-slate-200']

                };



                $recent .= '

                <a class="flex items-center justify-between border-b border-slate-100 p-4 hover:bg-slate-50/80 transition rounded-xl" href="?action=company_applications">

                    <div class="flex items-center gap-3">

                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-700 font-bold text-xs">' . strtoupper(substr($item['candidate_name'], 0, 1)) . '</div>

                        <div>

                            <h3 class="text-sm font-bold text-slate-900">' . self::e($item['candidate_name']) . '</h3>

                            <p class="text-xs text-slate-500 mt-0.5">' . self::e($item['title']) . '</p>

                        </div>

                    </div>

                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span>

                </a>';

            }



            $content .= $cards . '

            <div class="grid lg:grid-cols-[1.3fr_0.7fr] gap-6">

                <article class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm">

                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-2">

                        <div>

                            <h2 class="text-lg font-bold text-slate-900">Actividad Reciente</h2>

                            <p class="text-xs text-slate-500 mt-0.5">Últimos candidatos postulados a tus ofertas laborales</p>

                        </div>

                        <a class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] hover:underline" href="?action=company_applications">

                            Ver candidatos ' . self::iconArrowRight('w-3.5 h-3.5') . '

                        </a>

                    </div>

                    <div class="divide-y divide-slate-100">' . ($recent ?: '<div class="py-10 text-center text-sm text-slate-400">Aún no has recibido postulaciones. Publica una vacante para recibir perfiles.</div>') . '</div>

                </article>

                <div class="space-y-6">

                    <article class="rounded-3xl bg-gradient-to-br from-[#102a43] to-[#1e40af] p-6 text-white shadow-sm">

                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 mb-4">' . self::iconBriefcase('w-6 h-6 text-white') . '</div>

                        <h2 class="text-xl font-bold">Publica una nueva vacante</h2>

                        <p class="mt-2 text-sm text-white/80 leading-relaxed">Conecta con jóvenes calificados, revisa hojas de vida y gestiona el proceso de selección en un solo panel.</p>

                        <a href="?action=company_jobs#crear" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-[#102a43] transition hover:bg-slate-100 shadow-sm">

                            ' . self::iconPlus('w-4 h-4') . ' Crear vacante

                        </a>

                    </article>

                    <article class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm">

                        <h3 class="text-sm font-bold text-slate-900 mb-3 flex items-center gap-2">

                            ' . self::iconSpark('w-4 h-4 text-amber-500') . ' Buenas prácticas de contratación

                        </h3>

                        <ul class="space-y-2.5 text-xs text-slate-600">

                            <li class="flex items-start gap-2">' . self::iconCheck('w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5') . ' <span>Define claramente modalidad (remoto/híbrido) y rango salarial.</span></li>

                            <li class="flex items-start gap-2">' . self::iconCheck('w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5') . ' <span>Enfoca los requisitos en potencial y habilidades esenciales.</span></li>

                            <li class="flex items-start gap-2">' . self::iconCheck('w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5') . ' <span>Actualiza el estado de las postulaciones para dar certidumbre al postulante.</span></li>

                        </ul>

                    </article>

                </div>

            </div>';

        }



        return $content . '</section>';

    }


    public static function companyProfileView(array $company, array $user): string
    {

        return '

        <div class="max-w-4xl mx-auto">

            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

                <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-slate-100 mb-6">

                    <div>

                        <h2 class="text-2xl font-bold text-slate-900">Información Corporativa</h2>

                        <p class="text-sm text-slate-500 mt-1">Mantén actualizados los datos institucionales que verán los candidatos postulantes.</p>

                    </div>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">

                        ' . self::iconCheck('w-3.5 h-3.5') . ' Perfil Corporativo Verificado

                    </span>

                </div>

                <form method="post" action="?action=save_profile" enctype="multipart/form-data" class="space-y-6">

                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                    <div class="grid gap-5 md:grid-cols-2">

                        ' . self::fieldValue('name', 'Nombre de la empresa', $company['name']) . '

                        ' . self::fieldValue('city', 'Ciudad sede', $company['city']) . '

                        ' . self::fieldValue('industry', 'Sector o Industria', $company['industry']) . '

                        ' . self::fieldValue('size', 'Tamaño de la organización', $company['size']) . '

                        ' . self::fieldValue('website', 'Sitio web corporativo', $company['website'], 'url') . '

                        ' . self::fieldValue('phone', 'Teléfono de contacto', $company['phone']) . '

                        ' . self::fieldValue('contact_email', 'Correo de contacto corporativo', $company['contact_email'], 'email') . '

                        <div>

                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Logo o imagen corporativa</label>

                            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-[#17457c] hover:file:bg-blue-100 border border-slate-200 rounded-xl p-1.5 bg-slate-50/50">

                        </div>

                    </div>

                    <div>

                        ' . self::fieldValue('description', 'Descripción y cultura de la empresa', $company['description'], 'textarea') . '

                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end">

                        <button class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-7 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#12365f]">

                            ' . self::iconCheck('w-4 h-4') . ' Guardar perfil corporativo

                        </button>

                    </div>

                </form>

            </article>



            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm mt-6">

                <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-slate-100 mb-6">

                    <div>

                        <h2 class="text-2xl font-bold text-slate-900">Seguridad de la cuenta</h2>

                        <p class="text-sm text-slate-500 mt-1">Actualiza la contraseña con la que tu equipo de reclutamiento ingresa al portal.</p>

                    </div>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3.5 py-1 text-xs font-bold text-[#17457c] border border-blue-200">

                        ' . self::iconLock('w-3.5 h-3.5') . ' ' . self::e($user['email']) . '

                    </span>

                </div>

                ' . self::passwordChangeForm() . '

            </article>

        </div>' . self::passwordMatchScript();

    }


    /**

     * Perfil corporativo visto por un candidato que se postulo a sus vacantes.

     *

     * Nunca se muestra el numero de documento de la cuenta de la empresa.

     */

    public static function companyPublicProfile(array $company, ?array $user, ?string $message, ?string $error): string
    {

        $logo = !empty($company['profile_photo_path'])

            ? '<img src="?action=download_profile_photo&type=company&id=' . (int) $company['id'] . '" alt="Logo de la empresa" class="h-20 w-20 rounded-2xl object-cover border-2 border-slate-200 shadow-sm">'

            : '<div class="h-20 w-20 rounded-2xl flex items-center justify-center bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white font-extrabold text-2xl shadow-sm border border-blue-900/20">' . strtoupper(substr((string) $company['name'], 0, 1)) . '</div>';



        $facts = self::publicFact('Industria', $company['industry'] ?? null)

            . self::publicFact('Ciudad', $company['city'] ?? null)

            . self::publicFact('Tamano del equipo', $company['size'] ?? null)

            . self::publicFact('Telefono', $company['phone'] ?? null)

            . self::publicFact('Correo de contacto', $company['contact_email'] ?? null);



        $website = !empty($company['website'])

            ? '<a class="inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline break-all" href="' . self::e($company['website']) . '" target="_blank" rel="noopener noreferrer">' . self::e($company['website']) . '</a>'

            : '';



        return self::alerts($message, $error) . '

        <div class="max-w-4xl mx-auto pb-10">

            <a href="?action=applications" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-[#17457c] mb-5 transition">' . self::iconArrowLeft('w-3.5 h-3.5') . ' Volver a mis aplicaciones</a>

            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

                <div class="flex flex-col md:flex-row md:items-center gap-5 pb-6 border-b border-slate-100">

                    ' . $logo . '

                    <div class="flex-1">

                        <div class="flex flex-wrap items-center gap-2 mb-1.5">

                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200">' . self::iconBuilding('w-3.5 h-3.5') . ' Empresa</span>

                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">' . self::iconCheck('w-3.5 h-3.5') . ' Verificada</span>

                        </div>

                        <h1 class="text-2xl font-extrabold text-slate-900">' . self::e($company['name']) . '</h1>

                        <p class="text-sm text-slate-500 mt-1">' . self::e($company['city']) . '</p>

                    </div>

                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">' . $facts . '</div>

                ' . ($website ? '<div class="mt-5">' . $website . '</div>' : '') . '

            </article>

            ' . self::publicSection('Sobre la empresa', $company['description'] ?? null) . '

            <p class="mt-6 flex items-start gap-2 text-xs text-slate-400">' . self::iconShield('w-4 h-4') . ' Los datos de la cuenta de la empresa son privados y no se comparten con los candidatos.</p>

        </div>';

    }
}
