<?php

namespace Modules\Auditoria\UI;

use App\Http\PortalViewRenderer;

final class AuditoriaUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (registro de auditoria).
     */
    public static function styles(): string
    {
        return '
        /* Tabla de auditoria: filas alternas y aparicion escalonada */
        :where(#auditTable) tbody tr:nth-child(even) { background-color: #fbfcfe; }
        :where(#auditTable) tbody tr:nth-child(-n+10) { animation: yeb-row-in 0.24s ease-out backwards; }
        :where(#auditTable) tbody tr:nth-child(2) { animation-delay: 0.03s; }
        :where(#auditTable) tbody tr:nth-child(3) { animation-delay: 0.06s; }
        :where(#auditTable) tbody tr:nth-child(4) { animation-delay: 0.09s; }
        :where(#auditTable) tbody tr:nth-child(n+5) { animation-delay: 0.12s; }
        ';
    }

    public static function adminAuditView(array $counts, array $logs, array $modules, array $actions, array $filters): string
    {

        $metricCards = '

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Usuarios Registrados</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">' . self::iconUser('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . (int) ($counts['users'] ?? 0) . '</p>

                <a href="?action=dashboard&tab=users" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-[#17457c] hover:underline">Ver usuarios ' . self::iconArrowRight('w-3 h-3') . '</a>

            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Menores de Edad</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700">' . self::iconDocument('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . (int) ($counts['minors'] ?? 0) . '</p>

                <a href="?action=dashboard&tab=minors" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-[#17457c] hover:underline">Ver menores ' . self::iconArrowRight('w-3 h-3') . '</a>

            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Empresas Activas</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">' . self::iconBuilding('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . (int) ($counts['companies'] ?? 0) . '</p>

                <a href="?action=dashboard&tab=companies" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-[#17457c] hover:underline">Ver empresas ' . self::iconArrowRight('w-3 h-3') . '</a>

            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Vacantes Publicadas</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">' . self::iconBriefcase('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . (int) ($counts['jobs'] ?? 0) . '</p>

                <span class="mt-1 block text-xs font-medium text-slate-500">Ofertas en el portal</span>

            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Postulaciones Totales</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">' . self::iconDocument('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . (int) ($counts['applications'] ?? 0) . '</p>

                <span class="mt-1 block text-xs font-medium text-slate-500">Postulaciones registradas</span>

            </div>

        </div>';



        // Filter dropdown options

        $moduleOptions = '<option value="">Todos los módulos</option>';

        foreach ($modules as $m) {

            $sel = ($filters['module'] === $m) ? ' selected' : '';

            $moduleOptions .= '<option value="' . self::e($m) . '"' . $sel . '>' . self::e($m) . '</option>';

        }



        $actionOptions = '<option value="">Todas las acciones</option>';

        foreach ($actions as $a) {

            $sel = ($filters['action'] === $a) ? ' selected' : '';

            $actionOptions .= '<option value="' . self::e($a) . '"' . $sel . '>' . self::e($a) . '</option>';

        }



        $statusOptions = '<option value="">Todos los estados</option>

            <option value="success"' . ($filters['status'] === 'success' ? ' selected' : '') . '>Exitoso (success)</option>

            <option value="error"' . ($filters['status'] === 'error' ? ' selected' : '') . '>Error (error)</option>

            <option value="failed"' . ($filters['status'] === 'failed' ? ' selected' : '') . '>Fallido (failed)</option>';



        $hasFilters = !empty($filters['module']) || !empty($filters['status']) || !empty($filters['action']) || !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['search']);



        $filterForm = '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm mb-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-5">

                <div>

                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">

                        ' . self::iconFilter('w-4 h-4 text-[#17457c]') . ' Filtros de Búsqueda para Auditoría

                    </h2>

                    <p class="text-xs text-slate-500 mt-0.5">Filtra eventos por módulo, tipo de acción, estado y rango de fechas</p>

                </div>

                ' . ($hasFilters ? '<a href="?action=dashboard&tab=audit" class="inline-flex items-center gap-1 text-xs font-bold text-rose-600 hover:underline">Limpiar filtros</a>' : '') . '

            </div>

            <form method="get" action="./" id="auditFiltersForm" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

                <input type="hidden" name="action" value="dashboard">

                <input type="hidden" name="tab" value="audit">

                <div>

                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Búsqueda general</label>

                    <div class="relative">

                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>

                        <input id="auditFilterSearch" name="search" value="' . self::e($filters['search']) . '" placeholder="Usuario, descripción o IP..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">

                    </div>

                </div>

                <div>

                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Módulo</label>

                    <select id="auditFilterModule" name="module" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">

                        ' . $moduleOptions . '

                    </select>

                </div>

                <div>

                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Estado</label>

                    <select id="auditFilterStatus" name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">

                        ' . $statusOptions . '

                    </select>

                </div>

                <div>

                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Acción registrada</label>

                    <select id="auditFilterAction" name="log_action" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">

                        ' . $actionOptions . '

                    </select>

                </div>

                <div>

                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Fecha desde</label>

                    <input id="auditFilterDateFrom" type="date" name="date_from" value="' . self::e($filters['date_from']) . '" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">

                </div>

                <div>

                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Fecha hasta</label>

                    <input id="auditFilterDateTo" type="date" name="date_to" value="' . self::e($filters['date_to']) . '" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">

                </div>

                <div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-3 pt-2">

                    ' . ($hasFilters ? '<a href="?action=dashboard&tab=audit" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">Restablecer</a>' : '') . '

                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#12365f]">

                        ' . self::iconFilter('w-3.5 h-3.5') . ' Aplicar Filtros

                    </button>

                </div>

            </form>

        </div>';



        $rows = '';

        foreach ($logs as $log) {

            $statusBadge = match ($log['status'] ?? 'success') {

                'error', 'failed' => '<span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 border border-rose-200">' . self::iconAlert('w-3 h-3') . ' Error</span>',

                default => '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">' . self::iconCheck('w-3 h-3') . ' Exitoso</span>'

            };



            $moduleBadge = match ($log['module'] ?? '') {

                'Auth' => '<span class="rounded-md bg-purple-50 text-purple-700 px-2 py-0.5 text-xs font-semibold border border-purple-200">Auth</span>',

                'Jobs' => '<span class="rounded-md bg-emerald-50 text-emerald-700 px-2 py-0.5 text-xs font-semibold border border-emerald-200">Jobs</span>',

                'Companies' => '<span class="rounded-md bg-blue-50 text-blue-700 px-2 py-0.5 text-xs font-semibold border border-blue-200">Companies</span>',

                'Users' => '<span class="rounded-md bg-indigo-50 text-indigo-700 px-2 py-0.5 text-xs font-semibold border border-indigo-200">Users</span>',

                'Applications' => '<span class="rounded-md bg-amber-50 text-amber-700 px-2 py-0.5 text-xs font-semibold border border-amber-200">Applications</span>',

                default => '<span class="rounded-md bg-slate-100 text-slate-700 px-2 py-0.5 text-xs font-semibold border border-slate-200">' . self::e($log['module'] ?? 'Sistema') . '</span>'

            };



            $formattedDate = date('Y-m-d H:i:s', strtotime($log['created_at']));

            $userName = $log['user_name'] ?? 'Sistema';



            $rows .= '

            <tr class="audit-row border-t border-slate-100 hover:bg-slate-50/70 transition" data-module="' . self::e((string) ($log['module'] ?? '')) . '" data-action="' . self::e((string) ($log['action'] ?? '')) . '" data-status="' . self::e((string) ($log['status'] ?? 'success')) . '" data-date="' . self::e($formattedDate) . '">

                <td class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap font-mono">' . self::e($formattedDate) . '</td>

                <td class="px-4 py-3.5 font-semibold text-slate-800 text-sm">

                    <div class="flex items-center gap-2">

                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-600 text-xs">' . self::iconUser('w-3.5 h-3.5') . '</span>

                        <span>' . self::e($userName) . '</span>

                    </div>

                </td>

                <td class="px-4 py-3.5 text-xs font-bold text-slate-700 font-mono">' . self::e($log['action']) . '</td>

                <td class="px-4 py-3.5 text-xs">' . $moduleBadge . '</td>

                <td class="px-4 py-3.5 text-sm text-slate-600 max-w-md truncate" title="' . self::e($log['description']) . '">' . self::e($log['description']) . '</td>

                <td class="px-4 py-3.5 text-xs"><span class="font-mono bg-slate-100 text-slate-600 px-2 py-1 rounded border border-slate-200">' . self::e($log['ip_address']) . '</span></td>

                <td class="px-4 py-3.5 text-xs">' . $statusBadge . '</td>

            </tr>';

        }



        if (!$rows) {

            $rows = '<tr><td colspan="7" class="p-12 text-center text-sm text-slate-400">No se encontraron eventos de auditoría con los filtros aplicados.</td></tr>';

        }



        $emptyResultRow = (strpos($rows, 'No se encontraron eventos de auditoría') === false)

            ? '<tr id="auditNoResults" style="display:none;"><td colspan="7" class="p-12 text-center text-sm text-slate-400">No se encontraron eventos de auditoría con los filtros aplicados.</td></tr>'

            : '';



        return $metricCards . $filterForm . '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">

                <div>

                    <h2 class="text-xl font-bold text-slate-900">Registro de Auditoría</h2>

                    <p class="text-xs text-slate-500 mt-0.5">Historial cronológico de acciones ejecutadas por usuarios y servicios</p>

                </div>

                <div class="flex items-center gap-3">

                    <div class="relative w-full sm:w-72">

                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>

                        <input id="auditSearchInput" type="text" placeholder="Filtro rápido en pantalla..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">

                    </div>

                    <span id="auditResultCount" class="whitespace-nowrap rounded-xl bg-slate-100 border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">' . count($logs) . ' registros</span>

                </div>

            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100">

                <table id="auditTable" class="w-full min-w-[920px] text-left text-sm">

                    <thead class="bg-slate-50/80 border-b border-slate-200/80">

                        <tr class="text-xs font-bold uppercase tracking-wider text-slate-500">

                            <th class="px-4 py-3">Fecha y Hora</th>

                            <th class="px-4 py-3">Usuario</th>

                            <th class="px-4 py-3">Acción</th>

                            <th class="px-4 py-3">Módulo</th>

                            <th class="px-4 py-3">Descripción</th>

                            <th class="px-4 py-3">Dirección IP</th>

                            <th class="px-4 py-3">Estado</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">' . $rows . $emptyResultRow . '</tbody>

                </table>

            </div>

        </div>

        <script>

        (function () {

            function normalizeAuditText(value) {

                return String(value || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

            }

            function auditFilterValue(id) {

                var element = document.getElementById(id);

                return element ? normalizeAuditText(element.value.trim()) : "";

            }

            function filterAuditTable() {

                var search = auditFilterValue("auditFilterSearch");

                var quickSearch = auditFilterValue("auditSearchInput");

                var module = auditFilterValue("auditFilterModule");

                var status = auditFilterValue("auditFilterStatus");

                var action = auditFilterValue("auditFilterAction");

                var dateFrom = auditFilterValue("auditFilterDateFrom");

                var dateTo = auditFilterValue("auditFilterDateTo");

                var rows = document.querySelectorAll(".audit-row");

                var visibleCount = 0;

                rows.forEach(function (row) {

                    var matches = true;

                    var rowText = normalizeAuditText(row.textContent);

                    if (search !== "" && rowText.indexOf(search) === -1) {

                        matches = false;

                    }

                    if (quickSearch !== "" && rowText.indexOf(quickSearch) === -1) {

                        matches = false;

                    }

                    if (module !== "" && normalizeAuditText(row.getAttribute("data-module")) !== module) {

                        matches = false;

                    }

                    if (status !== "" && normalizeAuditText(row.getAttribute("data-status")) !== status) {

                        matches = false;

                    }

                    if (action !== "" && normalizeAuditText(row.getAttribute("data-action")) !== action) {

                        matches = false;

                    }

                    var rowDate = String(row.getAttribute("data-date") || "").slice(0, 10);

                    if (dateFrom !== "" && rowDate !== "" && rowDate < dateFrom) {

                        matches = false;

                    }

                    if (dateTo !== "" && rowDate !== "" && rowDate > dateTo) {

                        matches = false;

                    }

                    row.style.display = matches ? "" : "none";

                    if (matches) {

                        visibleCount++;

                    }

                });

                var counter = document.getElementById("auditResultCount");

                if (counter) {

                    counter.textContent = visibleCount + " registros";

                }

                var emptyRow = document.getElementById("auditNoResults");

                if (emptyRow) {

                    emptyRow.style.display = visibleCount === 0 ? "" : "none";

                }

            }

            function initAuditFilters() {

                var form = document.getElementById("auditFiltersForm");

                if (!form) {

                    return;

                }

                form.querySelectorAll("input, select").forEach(function (field) {

                    field.addEventListener("input", filterAuditTable);

                    field.addEventListener("change", filterAuditTable);

                });

                var quickInput = document.getElementById("auditSearchInput");

                if (quickInput) {

                    quickInput.addEventListener("input", filterAuditTable);

                }

                form.addEventListener("submit", function (event) {

                    event.preventDefault();

                    filterAuditTable();

                });

                filterAuditTable();

            }

            if (document.readyState === "loading") {

                document.addEventListener("DOMContentLoaded", initAuditFilters);

            } else {

                initAuditFilters();

            }

        })();

        </script>';

    }
}
