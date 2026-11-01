<?php

namespace Modules\Search\UI;

use App\Http\PortalViewRenderer;
use Modules\Dashboard\UI\DashboardUI;
use Modules\Search\Controllers\SearchController;

final class SearchUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (buscador y resultados).
     */
    public static function styles(): string
    {
        return '
        /* Entrada del buscador */
        .dashboard-shell > * { animation: yeb-rise 0.3s cubic-bezier(0.22, 0.61, 0.36, 1) backwards; }

        /* Campos de filtro: resalta el campo completo al escribir */
        .search-field { transition: border-color 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease; }
        .search-field:focus-within { border-color: #17457c; box-shadow: 0 0 0 3px rgba(23, 69, 124, 0.14); background-color: #ffffff; }
        .search-field:focus-within > span { color: #17457c; }

        /* Paneles divididos (lista izquierda y detalle derecho) a la misma distancia y altura fija con scroll interno */
        .search-split-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            align-items: stretch;
        }
        .search-split-panel {
            height: 600px;
            max-height: 600px;
            min-height: 480px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        @media (min-width: 768px) {
            .search-split-grid {
                grid-template-columns: minmax(300px, 42%) minmax(0, 58%);
            }
            .search-split-panel {
                height: clamp(540px, calc(100vh - 230px), 740px);
                max-height: clamp(540px, calc(100vh - 230px), 740px);
                min-height: 540px;
            }
        }
        .search-panel-scroll {
            flex: 1 1 auto;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        /* Resultados: cascada y acento en el titulo */
        .job-result-card { animation: yeb-rise 0.28s cubic-bezier(0.22, 0.61, 0.36, 1) backwards; }
        .job-result-card:nth-child(2) { animation-delay: 0.03s; }
        .job-result-card:nth-child(3) { animation-delay: 0.06s; }
        .job-result-card:nth-child(4) { animation-delay: 0.09s; }
        .job-result-card:nth-child(n+5) { animation-delay: 0.12s; }
        @media (hover: hover) { .job-result-card:hover h3 { color: #17457c; } }
        .job-result-card.is-selected {
            border-left: 4px solid #17457c;
            background: linear-gradient(90deg, #eff6ff 0%, #f8fafc 100%);
            box-shadow: inset 0 1px 0 0 rgba(59, 130, 246, 0.12);
        }
        ';
    }

    public static function candidateDashboard(array $user, ?string $message, ?string $error, array $searchJobs, SearchController $searchController, array $filters = [], ?array $userProfile = null): string
    {

        $html = self::alerts($message, $error);

        // Leer filtros avanzados (vienen de $filters si están presentes, sino desde $_GET)

        $fTitle = self::e($filters['title'] ?? $_GET['title'] ?? '');

        $fCity = self::e($filters['city'] ?? $_GET['city'] ?? '');

        $fWorkMode = self::e($filters['work_mode'] ?? $_GET['work_mode'] ?? '');

        $fExperience = self::e($filters['experience'] ?? $_GET['experience'] ?? '');

        $fSalaryMin = self::e($filters['salary_min'] ?? $_GET['salary_min'] ?? '');

        $fSalaryMax = self::e($filters['salary_max'] ?? $_GET['salary_max'] ?? '');

        $fCompany = self::e($filters['company'] ?? $_GET['company'] ?? '');

        // Indicador de auto-rellenado desde perfil

        $autoFilledTitle = (($fTitle !== '') && empty($_GET['title']) && !empty($userProfile['professional_title']));

        $autoFilledCity = (($fCity !== '') && empty($_GET['city']) && !empty($userProfile['location']));

        $rawJobParam = $_GET['job'] ?? '';
        $selectedId = ($rawJobParam !== '' && (int) $rawJobParam > 0)
            ? (int) $rawJobParam
            : (int) ($searchJobs[0]['id'] ?? 0);

        $hiddenIds = $searchController->hiddenJobIds($user['id']);

        $savedIds = $searchController->savedJobIds($user['id']);

        $jobs = array_values(array_filter($searchJobs, fn(array $job): bool => !in_array((int) $job['id'], $hiddenIds, true)));

        if (!$jobs)
            $selectedId = 0;

        $selected = null;
        $selectedIndex = 0;

        foreach ($jobs as $idx => $job) {
            if ((int) $job['id'] === $selectedId) {
                $selected = $job;
                $selectedIndex = $idx;
                break;
            }
        }

        if (!$selected && !empty($jobs)) {
            $selected = $jobs[0];
            $selectedIndex = 0;
        }

        $totalJobsCount = count($jobs);
        $prevJobId = ($totalJobsCount > 1) ? (int) $jobs[($selectedIndex - 1 + $totalJobsCount) % $totalJobsCount]['id'] : 0;
        $nextJobId = ($totalJobsCount > 1) ? (int) $jobs[($selectedIndex + 1) % $totalJobsCount]['id'] : 0;

        $jobCards = '';

        foreach ($jobs as $idx => $job) {

            $isSelected = $selected && (int) $selected['id'] === (int) $job['id'];

            $isSaved = in_array((int) $job['id'], $savedIds, true);

            $modeBadge = match (strtolower((string) ($job['work_mode'] ?? ''))) {
                'remote', 'remoto' => '<span class="inline-flex items-center rounded-full bg-purple-100/90 text-purple-800 border border-purple-200 px-2.5 py-0.5 text-[11px] font-bold">Remoto</span>',
                'hybrid', 'híbrido', 'hibrido' => '<span class="inline-flex items-center rounded-full bg-blue-100/90 text-blue-800 border border-blue-200 px-2.5 py-0.5 text-[11px] font-bold">Híbrido</span>',
                default => '<span class="inline-flex items-center rounded-full bg-teal-100/90 text-teal-800 border border-teal-200 px-2.5 py-0.5 text-[11px] font-bold">Presencial</span>',
            };

            $cardSalary = ($job['salary_min'] || $job['salary_max'])
                ? '$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.')
                : 'A convenir';

            $jobCards .= '

            <a href="?action=search' . self::buildSearchQuery($filters, (int) $job['id']) . '"
               data-job-id="' . (int) $job['id'] . '"
               data-job-index="' . ($idx + 1) . '"
               data-title="' . self::e((string) ($job['title'] ?? '')) . '"
               data-company="' . self::e((string) ($job['company_name'] ?? '')) . '"
               data-city="' . self::e((string) ($job['city'] ?? '')) . '"
               data-work-mode="' . self::e((string) ($job['work_mode'] ?? '')) . '"
               data-experience="' . self::e((string) ($job['experience'] ?? '')) . '"
               data-description="' . self::e((string) ($job['description'] ?? '')) . '"
               data-salary-min="' . self::e((string) ($job['salary_min'] ?? '')) . '"
               data-salary-max="' . self::e((string) ($job['salary_max'] ?? '')) . '"
               data-salary-formatted="' . self::e($cardSalary) . '"
               data-saved="' . ($isSaved ? '1' : '0') . '"
               class="job-result-card block border-b border-slate-200/90 p-4 sm:p-5 transition ' . ($isSelected ? 'is-selected' : 'bg-white hover:bg-slate-50/90') . '">

                <div class="flex items-start justify-between gap-3">

                    <div class="min-w-0 flex-1">

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#17457c] bg-blue-50 border border-blue-200/80 px-2 py-0.5 rounded-md">#' . ($idx + 1) . '</span>
                            ' . $modeBadge . '
                        </div>

                        <h3 class="mt-2 break-words text-base font-bold text-slate-900 leading-snug">' . self::e($job['title']) . '</h3>

                        <p class="mt-1 break-words text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                            ' . self::iconBuilding('w-3.5 h-3.5 text-slate-400 flex-shrink-0') . '
                            <span class="truncate">' . self::e($job['company_name']) . '</span>
                        </p>

                    </div>

                    <span class="flex-shrink-0 rounded-lg p-1.5 ' . ($isSaved ? 'bg-rose-50 border border-rose-200' : 'bg-slate-50 border border-slate-200/70') . '">' . self::iconHeart($isSaved, 'w-4 h-4') . '</span>

                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">

                    <span class="min-w-0 break-words text-slate-600 font-medium flex items-center gap-1">
                        ' . self::iconLocation('w-3.5 h-3.5 inline text-indigo-500 flex-shrink-0') . ' ' . self::e($job['city']) . '
                    </span>

                    <span class="inline-flex items-center rounded-md bg-emerald-50 border border-emerald-200/80 px-2 py-0.5 text-[11px] font-bold text-emerald-800">
                        ' . $cardSalary . '
                    </span>

                </div>

                <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between gap-3 text-[11px] text-slate-500">

                    <span class="min-w-0 break-words font-medium">' . self::iconClock('w-3 h-3 inline text-amber-500 mr-1') . self::e($job['experience']) . '</span>

                    <span class="flex-shrink-0 font-semibold text-slate-400">' . date('d M', strtotime($job['created_at'])) . '</span>

                </div>

            </a>';

        }

        $selectedSaved = $selected && in_array((int) $selected['id'], $savedIds, true);
        $selectedSalary = $selected
            ? (($selected['salary_min'] || $selected['salary_max'])
                ? '$' . number_format((float) $selected['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $selected['salary_max'], 0, ',', '.')
                : 'Salario a convenir')
            : '';

        $detail = $selected ? '
        <!-- Barra superior fija de navegación de la tarjeta de empleo -->
        <div class="shrink-0 border-b border-slate-200 bg-gradient-to-r from-slate-50 via-blue-50/40 to-indigo-50/30 px-5 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#17457c] px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white shadow-sm">
                    ' . self::iconBriefcase('w-3.5 h-3.5') . '
                    <span id="detailPositionBadge">Vacante ' . ($selectedIndex + 1) . ' de ' . $totalJobsCount . '</span>
                </span>
                <span id="detailWorkModeBadge" class="inline-flex items-center rounded-full bg-blue-100 border border-blue-200 px-2.5 py-0.5 text-xs font-bold text-[#17457c]">
                    ' . self::e($selected['work_mode']) . '
                </span>
            </div>
            <div class="flex items-center gap-1.5">
                <a id="detailPrevBtn" href="?action=search' . self::buildSearchQuery($filters, $prevJobId) . '" class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white hover:bg-blue-50 hover:border-blue-300 hover:text-[#17457c] px-3 py-1.5 text-xs font-bold text-slate-700 transition shadow-sm' . ($totalJobsCount <= 1 ? ' pointer-events-none opacity-40' : '') . '" title="Vacante anterior">
                    ' . self::iconArrowLeft('w-3.5 h-3.5') . ' Anterior
                </a>
                <a id="detailNextBtn" href="?action=search' . self::buildSearchQuery($filters, $nextJobId) . '" class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white hover:bg-blue-50 hover:border-blue-300 hover:text-[#17457c] px-3 py-1.5 text-xs font-bold text-slate-700 transition shadow-sm' . ($totalJobsCount <= 1 ? ' pointer-events-none opacity-40' : '') . '" title="Siguiente vacante">
                    Siguiente ' . self::iconArrowRight('w-3.5 h-3.5') . '
                </a>
            </div>
        </div>

        <!-- Cuerpo desplazable de la tarjeta del empleo -->
        <div id="searchDetailScrollBody" class="search-panel-scroll p-6 md:p-7 space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <h1 id="detailTitle" class="break-words text-2xl lg:text-3xl font-extrabold text-slate-900 leading-tight">' . self::e($selected['title']) . '</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2.5 text-sm">
                        <span id="detailCompany" class="inline-flex items-center gap-1.5 font-bold text-[#17457c] bg-blue-50/80 border border-blue-200/80 px-3 py-1 rounded-lg">
                            ' . self::iconBuilding('w-4 h-4') . '
                            <span>' . self::e($selected['company_name']) . '</span>
                        </span>
                        <span id="detailCity" class="inline-flex items-center gap-1 font-semibold text-slate-600 bg-slate-100 border border-slate-200 px-3 py-1 rounded-lg">
                            ' . self::iconLocation('w-4 h-4 text-indigo-600') . '
                            <span>' . self::e($selected['city']) . '</span>
                        </span>
                    </div>
                </div>
                <div class="hidden h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#17457c] to-[#2563eb] text-white shadow-md sm:flex">
                    ' . self::iconBriefcase('w-7 h-7') . '
                </div>
            </div>

            <!-- Botones de acción con colores semánticos -->
            <div class="flex flex-wrap items-center gap-2.5 rounded-2xl bg-slate-50 border border-slate-200/90 p-3.5">
                <form method="post" action="?action=apply" class="flex-1 sm:flex-none">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <input type="hidden" name="job_id" id="detailApplyJobId" value="' . (int) $selected['id'] . '">
                    <button class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700">
                        ' . self::iconCheck('w-4 h-4') . ' Aplicar a la vacante
                    </button>
                </form>

                <form method="post" action="?action=toggle_saved_job">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <input type="hidden" name="return_to" id="detailSaveReturnTo" value="' . self::e('?action=search' . self::buildSearchQuery($filters, (int) $selected['id'])) . '">
                    <input type="hidden" name="job_id" id="detailSaveJobId" value="' . (int) $selected['id'] . '">
                    <button class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50/90 hover:bg-rose-100 text-rose-700 px-3.5 py-2.5 text-xs font-bold transition shadow-sm" title="Guardar en favoritos">
                        ' . self::iconHeart($selectedSaved, 'w-4 h-4') . ' <span>Favorito</span>
                    </button>
                </form>

                <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50/90 hover:bg-sky-100 text-sky-800 px-3.5 py-2.5 text-xs font-bold transition shadow-sm" title="Copiar enlace" onclick="navigator.clipboard?.writeText(window.location.href);alert(\'Enlace copiado al portapapeles\');return false">
                    ' . self::iconShare('w-4 h-4 text-sky-700') . ' <span>Compartir</span>
                </button>

                <form method="post" action="?action=hide_job" class="ml-auto">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <input type="hidden" name="return_to" id="detailHideReturnTo" value="' . self::e('?action=search' . self::buildSearchQuery($filters, (int) $selected['id'])) . '">
                    <input type="hidden" name="job_id" id="detailHideJobId" value="' . (int) $selected['id'] . '">
                    <button class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50/90 hover:bg-amber-100 text-amber-800 px-3.5 py-2.5 text-xs font-bold transition shadow-sm" title="Ocultar vacante">
                        ' . self::iconEyeOff('w-4 h-4 text-amber-700') . ' <span>Ocultar</span>
                    </button>
                </form>
            </div>

            <!-- Tarjetas de resumen con contraste cromático -->
            <div class="grid gap-3.5 text-sm sm:grid-cols-3">
                <div class="min-w-0 rounded-2xl border border-indigo-200/80 bg-indigo-50/60 p-4">
                    <div class="flex items-center gap-2 text-indigo-700 font-bold text-xs uppercase tracking-wider">' . self::iconBriefcase('w-4 h-4') . '<span>Modalidad</span></div>
                    <p id="detailWorkMode" class="mt-1.5 break-words font-extrabold text-indigo-950 text-base">' . self::e($selected['work_mode']) . '</p>
                </div>

                <div class="min-w-0 rounded-2xl border border-amber-200/80 bg-amber-50/60 p-4">
                    <div class="flex items-center gap-2 text-amber-800 font-bold text-xs uppercase tracking-wider">' . self::iconClock('w-4 h-4') . '<span>Experiencia</span></div>
                    <p id="detailExperience" class="mt-1.5 break-words font-extrabold text-amber-950 text-base">' . self::e($selected['experience']) . '</p>
                </div>

                <div class="min-w-0 rounded-2xl border border-emerald-200/80 bg-emerald-50/60 p-4">
                    <div class="flex items-center gap-2 text-emerald-800 font-bold text-xs uppercase tracking-wider">' . self::iconCurrency('w-4 h-4') . '<span>Rango Salarial</span></div>
                    <p id="detailSalary" class="mt-1.5 break-words font-extrabold text-emerald-950 text-base">' . $selectedSalary . '</p>
                </div>
            </div>

            <!-- Descripción de la oferta en contenedor de lectura cómoda -->
            <div class="rounded-2xl border border-slate-200/90 bg-[#f8fafc] p-5 md:p-6">
                <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2 pb-3 border-b border-slate-200/80">
                    ' . self::iconDocument('w-5 h-5 text-[#17457c]') . '
                    <span>Descripción y requisitos de la oferta</span>
                </h2>
                <p id="detailDescription" class="mt-4 break-words whitespace-pre-line leading-7 text-slate-700 text-sm">' . self::e($selected['description']) . '</p>
            </div>
        </div>' : '<div class="flex h-full flex-col items-center justify-center p-12 text-center text-slate-500">
            <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-[#17457c]">' . self::iconSearch('w-7 h-7') . '</div>
            <p class="font-bold text-slate-800 text-base">No se encontraron vacantes disponibles</p>
            <p class="mt-1 text-xs text-slate-500">Intenta limpiar o ajustar los criterios de búsqueda en el panel superior.</p>
        </div>';

        return $html . '
        <section class="candidate-page dashboard-shell">
            ' . DashboardUI::candidateNav('search') . '

            <div class="mx-auto max-w-6xl space-y-5">
                <!-- Panel de filtros con mejor contraste y jerarquía visual -->
                <div class="rounded-3xl border border-slate-200/90 bg-white p-5 md:p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3.5 mb-4 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#17457c] text-white shadow-sm">
                                ' . self::iconSearch('w-4 h-4') . '
                            </span>
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900 leading-none">Buscador Avanzado de Empleos</h2>
                                <p class="text-xs text-slate-500 mt-1">Filtra en tiempo real y explora el detalle de cada vacante sin perder tu posición en la lista.</p>
                            </div>
                        </div>
                        <!-- Pills de acceso rápido con colores semánticos -->
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="text-slate-400 font-semibold mr-1 hidden sm:inline">Modalidad rápida:</span>
                            <a href="?action=search" class="filter-pill ' . ($fWorkMode === '' ? '!bg-[#17457c] !text-white !border-[#17457c]' : '') . '">Todas</a>
                            <a href="?action=search&work_mode=Remote" class="filter-pill !border-purple-200 !bg-purple-50/70 !text-purple-800 hover:!bg-purple-100">Remoto</a>
                            <a href="?action=search&work_mode=Hybrid" class="filter-pill !border-blue-200 !bg-blue-50/70 !text-blue-800 hover:!bg-blue-100">Híbrido</a>
                            <a href="?action=search&work_mode=Presencial" class="filter-pill !border-teal-200 !bg-teal-50/70 !text-teal-800 hover:!bg-teal-100">Presencial</a>
                        </div>
                    </div>

                    <form method="get" id="search-filters-form" class="space-y-3">
                        <input type="hidden" name="action" value="search">

                        <!-- Fila 1: Cargo + Ciudad + Empresa + Modalidad -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                            <div class="search-field flex items-center gap-2 rounded-xl border border-slate-300/90 bg-slate-50/60 px-3.5 py-2.5">
                                <span class="text-[#17457c] flex-shrink-0">' . self::iconSearch('w-4 h-4') . '</span>
                                <input id="jobSearchTitle" name="title" value="' . $fTitle . '" placeholder="Cargo o palabra clave"
                                       class="w-full bg-transparent text-sm text-slate-800 outline-none' . ($autoFilledTitle ? ' italic text-slate-500' : '') . '"
                                       title="' . ($autoFilledTitle ? 'Pre-rellenado desde tu perfil profesional' : '') . '">
                            </div>

                            <div class="search-field flex items-center gap-2 rounded-xl border border-slate-300/90 bg-slate-50/60 px-3.5 py-2.5">
                                <span class="text-indigo-600 flex-shrink-0">' . self::iconLocation('w-4 h-4') . '</span>
                                <input id="jobSearchCity" name="city" value="' . $fCity . '" placeholder="Ciudad (ej: Medellín)"
                                       class="w-full bg-transparent text-sm text-slate-800 outline-none' . ($autoFilledCity ? ' italic text-slate-500' : '') . '"
                                       title="' . ($autoFilledCity ? 'Pre-rellenado desde tu ubicación en el perfil' : '') . '">
                            </div>

                            <div class="search-field flex items-center gap-2 rounded-xl border border-slate-300/90 bg-slate-50/60 px-3.5 py-2.5">
                                <span class="text-amber-600 flex-shrink-0">' . self::iconBuilding('w-4 h-4') . '</span>
                                <input id="jobSearchCompany" name="company" value="' . $fCompany . '" placeholder="Empresa" class="w-full bg-transparent text-sm text-slate-800 outline-none">
                            </div>

                            <select id="jobSearchWorkMode" name="work_mode" class="search-field rounded-xl border border-slate-300/90 bg-slate-50/60 px-3.5 py-2.5 text-sm font-medium text-slate-700 outline-none cursor-pointer">
                                <option value="">▾ Todas las modalidades</option>
                                <option value="Remote"' . ($fWorkMode === 'Remote' ? ' selected' : '') . '>Remoto</option>
                                <option value="Hybrid"' . ($fWorkMode === 'Hybrid' ? ' selected' : '') . '>Híbrido</option>
                                <option value="Presencial"' . ($fWorkMode === 'Presencial' ? ' selected' : '') . '>Presencial</option>
                            </select>
                        </div>

                        <!-- Fila 2: Experiencia + Salario mín + Salario máx + Botones -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 items-center">
                            <div class="search-field flex items-center gap-2 rounded-xl border border-slate-300/90 bg-slate-50/60 px-3.5 py-2">
                                <span class="text-amber-600 flex-shrink-0">' . self::iconClock('w-4 h-4') . '</span>
                                <input id="jobSearchExperience" name="experience" value="' . $fExperience . '" placeholder="Experiencia requerida" class="w-full bg-transparent text-sm text-slate-800 outline-none">
                            </div>

                            <div class="search-field flex items-center gap-2 rounded-xl border border-slate-300/90 bg-slate-50/60 px-3.5 py-2">
                                <span class="text-emerald-600 flex-shrink-0 text-xs font-bold flex items-center gap-1">' . self::iconCurrency('w-4 h-4') . ' Mín</span>
                                <input id="jobSearchSalaryMin" name="salary_min" type="number" min="0" step="50000" value="' . $fSalaryMin . '" placeholder="0" class="w-full bg-transparent text-sm text-slate-800 outline-none">
                            </div>

                            <div class="search-field flex items-center gap-2 rounded-xl border border-slate-300/90 bg-slate-50/60 px-3.5 py-2">
                                <span class="text-emerald-600 flex-shrink-0 text-xs font-bold flex items-center gap-1">' . self::iconCurrency('w-4 h-4') . ' Máx</span>
                                <input id="jobSearchSalaryMax" name="salary_max" type="number" min="0" step="50000" value="' . $fSalaryMax . '" placeholder="Sin límite" class="w-full bg-transparent text-sm text-slate-800 outline-none">
                            </div>

                            <div class="flex items-center gap-2">
                                <button class="flex-1 rounded-xl bg-[#17457c] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#12365f] flex items-center justify-center gap-2">
                                    ' . self::iconSearch('w-4 h-4') . ' Filtrar
                                </button>
                                <button type="button" id="jobSearchClear" class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition' . (($fTitle || $fCity || $fWorkMode || $fExperience || $fSalaryMin || $fSalaryMax || $fCompany) ? '' : ' hidden') . '">Limpiar</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Contenedor dividido: Lista izquierda y Tarjeta derecha a la misma distancia y altura con barra de scroll -->
                <div class="search-split-grid">
                    <aside id="searchJobsAside" class="search-split-panel min-w-0 rounded-3xl border border-slate-200/90 bg-white shadow-sm">
                        <div class="shrink-0 border-b border-slate-200 bg-gradient-to-r from-[#17457c] to-[#1e56a0] px-5 py-3.5 flex items-center justify-between text-white">
                            <p class="text-sm font-bold flex items-center gap-2">
                                ' . self::iconBriefcase('w-4 h-4 text-amber-300') . '
                                <span><strong id="searchResultCount">' . count($jobs) . '</strong> ofertas disponibles</span>
                            </p>
                            <span class="text-[11px] font-semibold text-blue-100">Selecciona una oferta</span>
                        </div>
                        <div id="searchJobsListScroll" class="search-panel-scroll">
                            ' . $jobCards . '
                            <div id="searchNoResults" class="p-8 text-center text-sm text-slate-500" style="display:none;">No se encontraron vacantes con los filtros aplicados.</div>
                        </div>
                    </aside>

                    <article id="searchJobDetail" class="search-split-panel min-w-0 rounded-3xl border border-slate-200/90 bg-white shadow-sm">' . $detail . '</article>
                </div>
            </div>
        </section>

        <script>
        (function () {
            var form = document.getElementById("search-filters-form");
            if (!form) {
                return;
            }

            var fields = {
                title: document.getElementById("jobSearchTitle"),
                city: document.getElementById("jobSearchCity"),
                company: document.getElementById("jobSearchCompany"),
                workMode: document.getElementById("jobSearchWorkMode"),
                experience: document.getElementById("jobSearchExperience"),
                salaryMin: document.getElementById("jobSearchSalaryMin"),
                salaryMax: document.getElementById("jobSearchSalaryMax")
            };

            var cards = Array.prototype.slice.call(document.querySelectorAll(".job-result-card"));
            var countLabel = document.getElementById("searchResultCount");
            var emptyState = document.getElementById("searchNoResults");
            var clearButton = document.getElementById("jobSearchClear");

            // Asegurar que la tarjeta activa en la lista izquierda sea visible sin mover toda la ventana
            var selectedCard = document.querySelector(".job-result-card.is-selected");
            var listScroll = document.getElementById("searchJobsListScroll");
            if (selectedCard && listScroll) {
                var cardTop = selectedCard.offsetTop - listScroll.offsetTop;
                if (cardTop > listScroll.clientHeight * 0.6) {
                    listScroll.scrollTop = Math.max(0, cardTop - 80);
                }
            }

            function normalizeText(value) {
                return String(value || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            }

            function fieldValue(field) {
                return field ? field.value.trim() : "";
            }

            function buildQuery() {
                var params = new URLSearchParams();
                var values = {
                    title: fieldValue(fields.title),
                    city: fieldValue(fields.city),
                    company: fieldValue(fields.company),
                    work_mode: fieldValue(fields.workMode),
                    experience: fieldValue(fields.experience),
                    salary_min: fieldValue(fields.salaryMin),
                    salary_max: fieldValue(fields.salaryMax)
                };
                Object.keys(values).forEach(function (name) {
                    if (values[name] !== "") {
                        params.set(name, values[name]);
                    }
                });
                return params.toString();
            }

            function selectJobCardInPlace(card, pushHistory) {
                if (!card) return;
                var jobId = card.getAttribute("data-job-id") || "";
                if (!jobId) return;

                cards.forEach(function (c) {
                    c.classList.remove("is-selected");
                    c.classList.add("bg-white");
                });
                card.classList.add("is-selected");

                var visibleCards = cards.filter(function (c) { return c.style.display !== "none"; });
                var currentIdx = visibleCards.indexOf(card);
                if (currentIdx === -1) currentIdx = 0;
                var totalVis = visibleCards.length || 1;

                var posBadge = document.getElementById("detailPositionBadge");
                if (posBadge) posBadge.textContent = "Vacante " + (currentIdx + 1) + " de " + totalVis;

                var titleEl = document.getElementById("detailTitle");
                if (titleEl) titleEl.textContent = card.getAttribute("data-title") || "";

                var compEl = document.querySelector("#detailCompany span");
                if (compEl) compEl.textContent = card.getAttribute("data-company") || "";

                var cityEl = document.querySelector("#detailCity span");
                if (cityEl) cityEl.textContent = card.getAttribute("data-city") || "";

                var wmEl = document.getElementById("detailWorkMode");
                if (wmEl) wmEl.textContent = card.getAttribute("data-work-mode") || "";

                var wmBadge = document.getElementById("detailWorkModeBadge");
                if (wmBadge) wmBadge.textContent = card.getAttribute("data-work-mode") || "";

                var expEl = document.getElementById("detailExperience");
                if (expEl) expEl.textContent = card.getAttribute("data-experience") || "";

                var salEl = document.getElementById("detailSalary");
                if (salEl) salEl.textContent = card.getAttribute("data-salary-formatted") || "";

                var descEl = document.getElementById("detailDescription");
                if (descEl) descEl.textContent = card.getAttribute("data-description") || "";

                var query = buildQuery();
                var returnUrl = "?action=search" + (query !== "" ? "&" + query : "") + "&job=" + encodeURIComponent(jobId);

                var applyId = document.getElementById("detailApplyJobId");
                if (applyId) applyId.value = jobId;

                var saveId = document.getElementById("detailSaveJobId");
                if (saveId) saveId.value = jobId;
                var saveRet = document.getElementById("detailSaveReturnTo");
                if (saveRet) saveRet.value = returnUrl;

                var hideId = document.getElementById("detailHideJobId");
                if (hideId) hideId.value = jobId;
                var hideRet = document.getElementById("detailHideReturnTo");
                if (hideRet) hideRet.value = returnUrl;

                // Actualizar botones Anterior / Siguiente
                if (visibleCards.length > 1) {
                    var prevCard = visibleCards[(currentIdx - 1 + visibleCards.length) % visibleCards.length];
                    var nextCard = visibleCards[(currentIdx + 1) % visibleCards.length];
                    var prevBtn = document.getElementById("detailPrevBtn");
                    var nextBtn = document.getElementById("detailNextBtn");
                    if (prevBtn && prevCard) {
                        prevBtn.setAttribute("href", prevCard.getAttribute("href") || "#");
                        prevBtn.setAttribute("data-target-job", prevCard.getAttribute("data-job-id") || "");
                    }
                    if (nextBtn && nextCard) {
                        nextBtn.setAttribute("href", nextCard.getAttribute("href") || "#");
                        nextBtn.setAttribute("data-target-job", nextCard.getAttribute("data-job-id") || "");
                    }
                }

                // Volver al inicio del scroll interno de la tarjeta de detalle
                var detailBody = document.getElementById("searchDetailScrollBody");
                if (detailBody) detailBody.scrollTop = 0;

                if (pushHistory && window.history && window.history.replaceState) {
                    window.history.replaceState(null, "", window.location.pathname + returnUrl);
                }
            }

            cards.forEach(function (card) {
                card.addEventListener("click", function (event) {
                    if (event.ctrlKey || event.metaKey || event.shiftKey) return;
                    if (document.getElementById("detailTitle")) {
                        event.preventDefault();
                        selectJobCardInPlace(card, true);
                    }
                });
            });

            ["detailPrevBtn", "detailNextBtn"].forEach(function (btnId) {
                var btn = document.getElementById(btnId);
                if (!btn) return;
                btn.addEventListener("click", function (event) {
                    var visibleCards = cards.filter(function (c) { return c.style.display !== "none"; });
                    if (visibleCards.length <= 1) return;
                    event.preventDefault();
                    var active = document.querySelector(".job-result-card.is-selected");
                    var idx = visibleCards.indexOf(active);
                    if (idx === -1) idx = 0;
                    var nextIdx = (btnId === "detailPrevBtn")
                        ? (idx - 1 + visibleCards.length) % visibleCards.length
                        : (idx + 1) % visibleCards.length;
                    var targetCard = visibleCards[nextIdx];
                    if (targetCard) {
                        selectJobCardInPlace(targetCard, true);
                        targetCard.scrollIntoView({ block: "nearest", behavior: "smooth" });
                    }
                });
            });

            function filterCandidateJobs() {
                var title = normalizeText(fieldValue(fields.title));
                var city = normalizeText(fieldValue(fields.city));
                var company = normalizeText(fieldValue(fields.company));
                var workMode = normalizeText(fieldValue(fields.workMode));
                var experience = normalizeText(fieldValue(fields.experience));
                var salaryMin = parseFloat(fieldValue(fields.salaryMin));
                var salaryMax = parseFloat(fieldValue(fields.salaryMax));
                var visibleCount = 0;
                var query = buildQuery();
                var firstVisible = null;
                var selectedStillVisible = false;

                cards.forEach(function (card) {
                    var matches = true;
                    if (title !== "") {
                        var titleText = normalizeText((card.getAttribute("data-title") || "") + " " + (card.getAttribute("data-description") || ""));
                        if (titleText.indexOf(title) === -1) {
                            matches = false;
                        }
                    }
                    if (city !== "" && normalizeText(card.getAttribute("data-city")).indexOf(city) === -1) {
                        matches = false;
                    }
                    if (company !== "" && normalizeText(card.getAttribute("data-company")).indexOf(company) === -1) {
                        matches = false;
                    }
                    if (workMode !== "" && normalizeText(card.getAttribute("data-work-mode")).indexOf(workMode) === -1) {
                        matches = false;
                    }
                    if (experience !== "" && normalizeText(card.getAttribute("data-experience")).indexOf(experience) === -1) {
                        matches = false;
                    }
                    var cardSalaryMin = parseFloat(card.getAttribute("data-salary-min") || "");
                    var cardSalaryMax = parseFloat(card.getAttribute("data-salary-max") || "");
                    if (!isNaN(salaryMin) && !isNaN(cardSalaryMax) && cardSalaryMax < salaryMin) {
                        matches = false;
                    }
                    if (!isNaN(salaryMax) && !isNaN(cardSalaryMin) && cardSalaryMin > salaryMax) {
                        matches = false;
                    }
                    card.style.display = matches ? "" : "none";
                    var jobId = card.getAttribute("data-job-id") || "";
                    if (jobId !== "") {
                        card.setAttribute("href", "?action=search" + (query !== "" ? "&" + query : "") + "&job=" + encodeURIComponent(jobId));
                    }
                    if (matches) {
                        visibleCount++;
                        if (!firstVisible) firstVisible = card;
                        if (card.classList.contains("is-selected")) selectedStillVisible = true;
                    }
                });

                if (!selectedStillVisible && firstVisible) {
                    selectJobCardInPlace(firstVisible, false);
                }

                if (countLabel) {
                    countLabel.textContent = String(visibleCount);
                }
                if (emptyState) {
                    emptyState.style.display = visibleCount === 0 ? "" : "none";
                }
                if (clearButton) {
                    clearButton.classList.toggle("hidden", query === "");
                }
            }

            function syncCandidateUrl() {
                var query = buildQuery();
                var active = document.querySelector(".job-result-card.is-selected");
                var job = (active && active.getAttribute("data-job-id")) || new URLSearchParams(window.location.search).get("job") || "";
                var target = "?action=search";
                if (query !== "") {
                    target += "&" + query;
                }
                if (job !== "") {
                    target += "&job=" + encodeURIComponent(job);
                }
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, "", window.location.pathname + target);
                }
            }

            form.querySelectorAll("input, select").forEach(function (field) {
                field.addEventListener("input", filterCandidateJobs);
                field.addEventListener("change", filterCandidateJobs);
            });

            form.addEventListener("submit", function (event) {
                event.preventDefault();
                filterCandidateJobs();
                syncCandidateUrl();
            });

            if (clearButton) {
                clearButton.addEventListener("click", function () {
                    Object.keys(fields).forEach(function (key) {
                        if (fields[key]) {
                            fields[key].value = "";
                        }
                    });
                    filterCandidateJobs();
                    syncCandidateUrl();
                });
            }

            document.querySelectorAll(".filter-pill").forEach(function (link) {
                link.addEventListener("click", function (event) {
                    event.preventDefault();
                    var target = new URL(link.href, window.location.href);
                    if (fields.workMode) {
                        fields.workMode.value = target.searchParams.get("work_mode") || "";
                    }
                    filterCandidateJobs();
                    syncCandidateUrl();
                });
            });

            filterCandidateJobs();
        })();
        </script>';
    }

    public static function candidateFavorites(array $user, ?string $message, ?string $error, SearchController $searchController): string
    {

        $rows = '';

        foreach ($searchController->savedJobs($user['id']) as $job) {

            $rows .= '
            <a class="application-card border-l-4 !border-l-rose-500 hover:border-rose-300 transition group" href="?action=search&job=' . $job['id'] . '">
                <div>
                    <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-rose-700 mb-1">Guardada en favoritos</span>
                    <h2 class="group-hover:text-[#17457c] transition">' . self::e($job['title']) . '</h2>
                    <p class="flex items-center gap-1.5">' . self::iconBuilding('w-3.5 h-3.5 text-slate-400') . ' ' . self::e($job['company_name']) . ' · ' . self::iconLocation('w-3.5 h-3.5 text-indigo-500') . ' ' . self::e($job['city']) . '</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700">' . self::iconHeart(true, 'w-5 h-5') . ' Ver oferta</span>
            </a>';

        }

        return self::alerts($message, $error) . '<section class="candidate-page">' . DashboardUI::candidateNav('favorites') . '<h1 class="page-title">Mis favoritos</h1><div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 border border-rose-200 mb-3">' . self::iconHeart(false, 'w-7 h-7 text-rose-500') . '</div><h2>Todavía no tienes ofertas guardadas</h2><p class="text-slate-600 text-sm mb-4">Guarda vacantes de tu interés para revisarlas más tarde.</p><a href="?action=search" class="primary-button">Buscar empleos</a></div>') . '</div></section>';

    }

    /**
     * Construye la query string con todos los filtros de búsqueda avanzada.
     * Si se pasa $jobId, agrega el parámetro &job=...
     */
    public static function buildSearchQuery(array $filters, int $jobId = 0): string
    {

        $params = [];

        $keys = ['title', 'city', 'work_mode', 'experience', 'salary_min', 'salary_max', 'company'];

        foreach ($keys as $k) {

            $v = trim((string) ($filters[$k] ?? ''));

            if ($v !== '')
                $params[$k] = $v;

        }

        if ($jobId > 0)
            $params['job'] = (string) $jobId;

        return $params ? '&' . http_build_query($params) : '';

    }
}
