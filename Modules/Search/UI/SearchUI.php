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
        .search-field:focus-within { border-color: #17457c; box-shadow: 0 0 0 3px rgba(23, 69, 124, 0.12); }
        .search-field:focus-within > span { color: #17457c; }

        /* Resultados: cascada y acento en el titulo */
        .job-result-card { animation: yeb-rise 0.28s cubic-bezier(0.22, 0.61, 0.36, 1) backwards; }
        .job-result-card:nth-child(2) { animation-delay: 0.03s; }
        .job-result-card:nth-child(3) { animation-delay: 0.06s; }
        .job-result-card:nth-child(4) { animation-delay: 0.09s; }
        .job-result-card:nth-child(n+5) { animation-delay: 0.12s; }
        @media (hover: hover) { .job-result-card:hover h3 { color: #17457c; } }
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

        $selectedId = (int) ($_GET['job'] ?? ($searchJobs[0]['id'] ?? 0));

        $hiddenIds = $searchController->hiddenJobIds($user['id']);

        $savedIds = $searchController->savedJobIds($user['id']);

        $jobs = array_values(array_filter($searchJobs, fn(array $job): bool => !in_array((int) $job['id'], $hiddenIds, true)));

        if (!$jobs)
            $selectedId = 0;

        $selected = null;

        foreach ($jobs as $job)
            if ((int) $job['id'] === $selectedId)
                $selected = $job;

        $selected ??= $jobs[0] ?? null;



        $jobCards = '';

        foreach ($jobs as $job) {

            $isSelected = $selected && (int) $selected['id'] === (int) $job['id'];

            $isSaved = in_array((int) $job['id'], $savedIds, true);

            $jobCards .= '

            <a href="?action=search' . self::buildSearchQuery($filters, $job['id']) . '" data-job-id="' . (int) $job['id'] . '" data-title="' . self::e((string) ($job['title'] ?? '')) . '" data-company="' . self::e((string) ($job['company_name'] ?? '')) . '" data-city="' . self::e((string) ($job['city'] ?? '')) . '" data-work-mode="' . self::e((string) ($job['work_mode'] ?? '')) . '" data-experience="' . self::e((string) ($job['experience'] ?? '')) . '" data-description="' . self::e((string) ($job['description'] ?? '')) . '" data-salary-min="' . self::e((string) ($job['salary_min'] ?? '')) . '" data-salary-max="' . self::e((string) ($job['salary_max'] ?? '')) . '" class="job-result-card block border-b border-slate-200 p-5 transition ' . ($isSelected ? 'border-l-4 border-l-[#17457c] bg-white shadow-sm' : 'bg-white hover:bg-slate-50') . '">

                <div class="flex items-start justify-between gap-3">

                    <div class="min-w-0">

                        <p class="text-xs font-bold uppercase tracking-wider text-[#17457c]">Oportunidad</p>

                        <h3 class="mt-1 break-words text-base font-bold text-slate-900">' . self::e($job['title']) . '</h3>

                        <p class="mt-1 break-words text-sm font-medium text-slate-600">' . self::e($job['company_name']) . '</p>

                    </div>

                    <span class="flex-shrink-0 p-1">' . self::iconHeart($isSaved, 'w-5 h-5') . '</span>

                </div>

                <p class="mt-2.5 text-xs text-slate-500 flex items-center gap-2">

                    <span class="min-w-0 break-words">' . self::iconLocation('w-3.5 h-3.5 inline text-slate-400') . ' ' . self::e($job['city']) . '</span>

                    <span class="flex-shrink-0">·</span>

                    <span class="flex-shrink-0">' . self::e($job['work_mode']) . '</span>

                </p>

                <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-400">

                    <span class="min-w-0 break-words">' . self::e($job['experience']) . '</span>

                    <span class="flex-shrink-0">' . date('d M', strtotime($job['created_at'])) . '</span>

                </div>

            </a>';

        }



        $detail = $selected ? '

        <div class="flex items-start justify-between gap-5">

            <div class="min-w-0">

                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold uppercase tracking-wider text-[#17457c] border border-blue-200">Oportunidad seleccionada</span>

                <h1 class="mt-2 break-words text-2xl lg:text-3xl font-extrabold text-slate-900">' . self::e($selected['title']) . '</h1>

                <p class="mt-1.5 break-words text-base font-semibold text-[#17457c]">' . self::e($selected['company_name']) . '</p>

                <p class="mt-1 break-words text-sm text-slate-500">' . self::iconLocation('w-4 h-4 inline text-slate-400') . ' ' . self::e($selected['city']) . '</p>

            </div>

            <div class="hidden h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-[#17457c] sm:flex">' . self::iconBriefcase('w-7 h-7') . '</div>

        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">

            <form method="post" action="?action=apply">

                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                <input type="hidden" name="job_id" value="' . $selected['id'] . '">

                <button class="rounded-xl bg-[#17457c] px-7 py-2.5 font-bold text-white shadow-sm transition hover:bg-[#12365f]">Aplicar a la vacante</button>

            </form>

            <form method="post" action="?action=toggle_saved_job">

                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                <input type="hidden" name="return_to" value="' . self::e('?action=search' . self::buildSearchQuery($filters, (int) $selected['id'])) . '">

                <input type="hidden" name="job_id" value="' . $selected['id'] . '">

                <button class="circle-action" title="Guardar vacante">' . self::iconHeart(in_array((int) $selected['id'], $savedIds, true), 'w-5 h-5') . '</button>

            </form>

            <button class="circle-action" title="Copiar enlace" onclick="navigator.clipboard?.writeText(window.location.href);alert(\'Enlace copiado al portapapeles\');return false">' . self::iconShare('w-5 h-5 text-[#17457c]') . '</button>

            <form method="post" action="?action=hide_job">

                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                <input type="hidden" name="return_to" value="' . self::e('?action=search' . self::buildSearchQuery($filters, (int) $selected['id'])) . '">

                <input type="hidden" name="job_id" value="' . $selected['id'] . '">

                <button class="circle-action" title="Ocultar vacante">' . self::iconEyeOff('w-5 h-5 text-slate-500') . '</button>

            </form>

        </div>

        <div class="my-6 border-t border-slate-200"></div>

        <div class="grid gap-4 text-sm text-slate-700 sm:grid-cols-3">

            <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">

                <div class="flex items-center gap-2 text-slate-500">' . self::iconBriefcase('w-4 h-4') . '<span class="text-xs">Modalidad</span></div>

                <p class="mt-1 break-words font-semibold text-slate-800">' . self::e($selected['work_mode']) . '</p>

            </div>

            <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">

                <div class="flex items-center gap-2 text-slate-500">' . self::iconClock('w-4 h-4') . '<span class="text-xs">Experiencia</span></div>

                <p class="mt-1 break-words font-semibold text-slate-800">' . self::e($selected['experience']) . '</p>

            </div>

            <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">

                <div class="flex items-center gap-2 text-slate-500">' . self::iconCurrency('w-4 h-4') . '<span class="text-xs">Rango Salarial</span></div>

                <p class="mt-1 break-words font-semibold text-slate-800">$' . number_format((float) $selected['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $selected['salary_max'], 0, ',', '.') . '</p>

            </div>

        </div>

        <div class="mt-7 border-t border-slate-200 pt-6">

            <h2 class="text-lg font-bold text-slate-900">Descripción de la oferta</h2>

            <p class="mt-3.5 break-words whitespace-pre-line leading-7 text-slate-600 text-sm">' . self::e($selected['description']) . '</p>

        </div>' : '<div class="flex h-full items-center justify-center p-12 text-center text-slate-400">No se encontraron vacantes con los criterios de búsqueda.</div>';



        return $html . '

        <section class="dashboard-shell -mx-5 min-h-[calc(100vh-78px)] bg-[#f4f7fb] md:-mx-8">

            <div class="border-b border-slate-200 bg-white px-5 py-4 md:px-8">

                <form method="get" class="mx-auto max-w-7xl" id="search-filters-form">

                    <input type="hidden" name="action" value="search">

                    <!-- Fila 1: Cargo + Ciudad + Empresa -->

                    <div class="flex flex-wrap gap-2 mb-2">

                        <div class="search-field flex-1 min-w-[180px] flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">

                            <span class="text-slate-400 flex-shrink-0">' . self::iconSearch('w-4 h-4') . '</span>

                            <input id="jobSearchTitle" name="title" value="' . $fTitle . '" placeholder="Título del cargo"

                                   class="w-full text-sm outline-none' . ($autoFilledTitle ? ' italic text-slate-500' : '') . '"

                                   title="' . ($autoFilledTitle ? 'Pre-rellenado desde tu perfil profesional' : '') . '">

                        </div>

                        <div class="search-field flex-1 min-w-[160px] flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">

                            <span class="text-slate-400 flex-shrink-0">' . self::iconLocation('w-4 h-4') . '</span>

                            <input id="jobSearchCity" name="city" value="' . $fCity . '" placeholder="Ciudad"

                                   class="w-full text-sm outline-none' . ($autoFilledCity ? ' italic text-slate-500' : '') . '"

                                   title="' . ($autoFilledCity ? 'Pre-rellenado desde tu ubicación en el perfil' : '') . '">

                        </div>

                        <div class="search-field flex-1 min-w-[180px] flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">

                            <span class="text-slate-400 flex-shrink-0">' . self::iconBuilding('w-4 h-4') . '</span>

                            <input id="jobSearchCompany" name="company" value="' . $fCompany . '" placeholder="Empresa" class="w-full text-sm outline-none">

                        </div>

                    </div>

                    <!-- Fila 2: Modalidad + Experiencia + Salario mín + Salario máx + Botón -->

                    <div class="flex flex-wrap gap-2 items-center">

                        <select id="jobSearchWorkMode" name="work_mode" class="search-field rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 outline-none min-w-[140px] cursor-pointer">

                            <option value="">' . ($fWorkMode === '' ? '▾ Modalidad' : '▾ Modalidad') . '</option>

                            <option value="Remote"' . ($fWorkMode === 'Remote' ? ' selected' : '') . '>Remoto</option>

                            <option value="Hybrid"' . ($fWorkMode === 'Hybrid' ? ' selected' : '') . '>Híbrido</option>

                            <option value="Presencial"' . ($fWorkMode === 'Presencial' ? ' selected' : '') . '>Presencial</option>

                        </select>

                        <div class="search-field flex-1 min-w-[160px] flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">

                            <span class="text-slate-400 flex-shrink-0">' . self::iconClock('w-4 h-4') . '</span>

                            <input id="jobSearchExperience" name="experience" value="' . $fExperience . '" placeholder="Experiencia requerida" class="w-full text-sm outline-none">

                        </div>

                        <div class="search-field flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">

                            <span class="text-slate-400 flex-shrink-0 text-xs font-semibold">' . self::iconCurrency('w-4 h-4') . ' Sal. min</span>

                            <input id="jobSearchSalaryMin" name="salary_min" type="number" min="0" step="50000" value="' . $fSalaryMin . '" placeholder="0" class="w-24 text-sm outline-none">

                        </div>

                        <div class="search-field flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2">

                            <span class="text-slate-400 flex-shrink-0 text-xs font-semibold">Sal. máx</span>

                            <input id="jobSearchSalaryMax" name="salary_max" type="number" min="0" step="50000" value="' . $fSalaryMax . '" placeholder="Sin límite" class="w-24 text-sm outline-none">

                        </div>

                        <button class="rounded-full bg-[#17457c] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#12365f] flex items-center gap-2">

                            ' . self::iconSearch('w-4 h-4') . ' Buscar

                        </button>

                        <button type="button" id="jobSearchClear" class="text-xs text-slate-500 hover:text-[#17457c] underline' . (($fTitle || $fCity || $fWorkMode || $fExperience || $fSalaryMin || $fSalaryMax || $fCompany) ? '' : ' hidden') . '">Limpiar filtros</button>

                    </div>

                </form>

                <!-- Pills de acceso rápido -->

                <div class="mx-auto mt-3 flex max-w-7xl gap-2 overflow-x-auto pb-1 text-xs">

                    <a href="?action=search" class="filter-pill">Todas</a>

                    <a href="?action=search&work_mode=Remote" class="filter-pill">Remoto</a>

                    <a href="?action=search&work_mode=Hybrid" class="filter-pill">Híbrido</a>

                    <a href="?action=search&work_mode=Presencial" class="filter-pill">Presencial</a>

                </div>

            </div>

            <div class="mx-auto grid max-w-7xl lg:grid-cols-[minmax(330px,38%)_1fr]">

                <aside class="min-w-0 max-h-[calc(100vh-210px)] overflow-y-auto border-r border-slate-200 bg-white">

                    <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3.5">

                        <p class="text-sm font-semibold text-slate-700"><strong id="searchResultCount">' . count($jobs) . '</strong> ofertas disponibles</p>

                    </div>

                    ' . $jobCards . '

                    <div id="searchNoResults" class="p-8 text-center text-sm text-slate-400" style="display:none;">No se encontraron vacantes con los filtros aplicados.</div>

                </aside>

                <article class="min-w-0 min-h-[calc(100vh-210px)] bg-white p-6 md:p-8">' . $detail . '</article>

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

                    }

                });

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

                var job = new URLSearchParams(window.location.search).get("job") || "";

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

            $rows .= '<a class="application-card hover:border-slate-300 transition" href="?action=search&job=' . $job['id'] . '"><div><h2>' . self::e($job['title']) . '</h2><p>' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p></div><span>' . self::iconHeart(true, 'w-5 h-5') . '</span></a>';

        }

        return self::alerts($message, $error) . '<section class="candidate-page">' . DashboardUI::candidateNav('favorites') . '<h1 class="page-title">Mis favoritos</h1><div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 mb-3">' . self::iconHeart(false, 'w-7 h-7 text-slate-400') . '</div><h2>Todavía no tienes ofertas guardadas</h2><p class="text-slate-500 text-sm mb-4">Guarda vacantes de tu interés para revisarlas más tarde.</p><a href="?action=search" class="primary-button">Buscar empleos</a></div>') . '</div></section>';

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
