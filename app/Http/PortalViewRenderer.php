<?php

namespace App\Http;

use Modules\Applications\Controllers\ApplicationsController;
use Modules\Chat\Controllers\ChatController;
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
            $tab = $_GET['tab'] ?? 'audit';
            $filters = [
                'module' => trim((string) ($_GET['module'] ?? '')),
                'status' => trim((string) ($_GET['status'] ?? '')),
                'action' => trim((string) ($_GET['log_action'] ?? '')),
                'date_from' => trim((string) ($_GET['date_from'] ?? '')),
                'date_to' => trim((string) ($_GET['date_to'] ?? '')),
                'search' => trim((string) ($_GET['search'] ?? '')),
            ];

            $summary = $dashboardController->summary($filters);
            $counts = $summary['counts'];

            $adminHeader = '
            <div class="mb-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-[#17457c] border border-blue-200 mb-2">
                            ' . self::iconShield('w-3.5 h-3.5') . ' Módulo de Auditoría y Control Administrativo
                        </div>
                        <h1 class="display text-3xl md:text-4xl font-extrabold text-slate-900">Panel de Control Administrativo</h1>
                        <p class="mt-1 text-slate-500 text-sm">Supervisión integral de usuarios, empresas registradas, métricas del portal y trazabilidad de eventos.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Sistema en Línea
                        </span>
                    </div>
                </div>
            </div>' . self::adminNav($tab);

            if ($tab === 'users') {
                $tabContent = self::adminUsersView($summary['users'], $user);
            } elseif ($tab === 'companies') {
                $tabContent = self::adminCompaniesView($summary['companies']);
            } elseif ($tab === 'create_admin') {
                $tabContent = self::adminCreateAdminView($summary['users']);
            } else {
                $tabContent = self::adminAuditView($counts, $summary['logs'], $summary['modules'], $summary['actions'], $filters);
            }

            return $html . $adminHeader . $tabContent;
        }

        return self::candidateDashboard($user, $message, $error, $searchJobs, $searchController);
    }

    private static function adminNav(string $active): string
    {
        $items = [
            'audit' => ['label' => 'Auditoría y Métricas', 'icon' => self::iconShield('w-4 h-4')],
            'users' => ['label' => 'Gestión de Usuarios', 'icon' => self::iconUser('w-4 h-4')],
            'companies' => ['label' => 'Gestión de Empresas', 'icon' => self::iconBuilding('w-4 h-4')],
            'create_admin' => ['label' => 'Crear Administrador', 'icon' => self::iconUserPlus('w-4 h-4')],
        ];
        $html = '<nav class="profile-tabs admin-tabs flex flex-wrap gap-2 p-1.5 rounded-2xl bg-slate-100 mb-8 border border-slate-200/80">';
        foreach ($items as $key => $item) {
            $isActive = ($key === $active);
            $class = $isActive 
                ? 'inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition' 
                : 'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-white/80 transition';
            $html .= '<a class="' . $class . '" href="?action=dashboard&tab=' . $key . '">' . $item['icon'] . '<span>' . $item['label'] . '</span></a>';
        }
        $html .= '</nav>';
        return $html;
    }

    private static function adminAuditView(array $counts, array $logs, array $modules, array $actions, array $filters): string
    {
        $metricCards = '
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
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
            <form method="get" action="./" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <input type="hidden" name="action" value="dashboard">
                <input type="hidden" name="tab" value="audit">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Búsqueda general</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>
                        <input name="search" value="' . self::e($filters['search']) . '" placeholder="Usuario, descripción o IP..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Módulo</label>
                    <select name="module" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">
                        ' . $moduleOptions . '
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Estado</label>
                    <select name="status" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">
                        ' . $statusOptions . '
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Acción registrada</label>
                    <select name="log_action" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">
                        ' . $actionOptions . '
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Fecha desde</label>
                    <input type="date" name="date_from" value="' . self::e($filters['date_from']) . '" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Fecha hasta</label>
                    <input type="date" name="date_to" value="' . self::e($filters['date_to']) . '" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] bg-white">
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
            $statusBadge = match($log['status'] ?? 'success') {
                'error', 'failed' => '<span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 border border-rose-200">' . self::iconAlert('w-3 h-3') . ' Error</span>',
                default => '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">' . self::iconCheck('w-3 h-3') . ' Exitoso</span>'
            };

            $moduleBadge = match($log['module'] ?? '') {
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
            <tr class="audit-row border-t border-slate-100 hover:bg-slate-50/70 transition">
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
                        <input id="auditSearchInput" onkeyup="filterAuditTable()" type="text" placeholder="Filtro rápido en pantalla..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">
                    </div>
                    <span class="whitespace-nowrap rounded-xl bg-slate-100 border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">' . count($logs) . ' registros</span>
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
                    <tbody class="divide-y divide-slate-100 bg-white">' . $rows . '</tbody>
                </table>
            </div>
        </div>
        <script>
        function filterAuditTable() {
            var input = document.getElementById("auditSearchInput");
            var filter = input.value.toLowerCase();
            var rows = document.querySelectorAll(".audit-row");
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        }
        </script>';
    }

    private static function adminUsersView(array $users, array $currentUser): string
    {
        $rows = '';
        foreach ($users as $u) {
            $isSelf = ((int) $u['id'] === (int) $currentUser['id']);

            $roleBadge = match($u['role']) {
                'admin' => '<span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200">' . self::iconShield('w-3 h-3') . ' Administrador</span>',
                'recruiter' => '<span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 border border-indigo-200">' . self::iconBuilding('w-3 h-3') . ' Empresa</span>',
                default => '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">' . self::iconUser('w-3 h-3') . ' Candidato</span>'
            };

            $actionBtn = $isSelf 
                ? '<span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">Sesión actual</span>'
                : '<form method="post" action="?action=admin_delete_user" class="inline" onsubmit="return confirm(\'¿Estás seguro de eliminar al usuario \\\' ' . addslashes(self::e($u['name'])) . ' \\\'? Esta acción eliminará permanentemente su cuenta, postulaciones y registros vinculados.\')">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <input type="hidden" name="user_id" value="' . $u['id'] . '">
                    <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 hover:border-rose-300 transition shadow-sm">
                        ' . self::iconTrash('w-3.5 h-3.5') . ' Eliminar
                    </button>
                </form>';

            $companyAffiliation = $u['company_name'] 
                ? '<span class="font-semibold text-slate-800">' . self::e($u['company_name']) . '</span>'
                : '<span class="text-slate-400">—</span>';

            $dateFormatted = date('Y-m-d', strtotime($u['created_at']));

            $rows .= '
            <tr class="user-row border-t border-slate-100 hover:bg-slate-50/70 transition">
                <td class="px-4 py-3.5 text-xs text-slate-400 font-mono">#' . $u['id'] . '</td>
                <td class="px-4 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 font-extrabold text-xs">' . strtoupper(substr($u['name'], 0, 1)) . '</div>
                        <div>
                            <p class="font-bold text-sm text-slate-900">' . self::e($u['name']) . '</p>
                            <p class="text-xs text-slate-500">' . self::e($u['email']) . '</p>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3.5 text-xs">' . $roleBadge . '</td>
                <td class="px-4 py-3.5 text-xs">' . $companyAffiliation . '</td>
                <td class="px-4 py-3.5 text-xs text-slate-500 font-mono">' . $dateFormatted . '</td>
                <td class="px-4 py-3.5 text-right whitespace-nowrap">' . $actionBtn . '</td>
            </tr>';
        }

        return '
        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-slate-100">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Gestión de Usuarios</h2>
                    <p class="text-xs text-slate-500 mt-1">Supervisa y administra todas las cuentas de candidatos, empresas y administradores del portal.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative w-full sm:w-72">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>
                        <input id="userSearchInput" onkeyup="filterUserTable()" type="text" placeholder="Buscar por nombre, correo o rol..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">
                    </div>
                    <span class="whitespace-nowrap rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700">' . count($users) . ' usuarios</span>
                </div>
            </div>
            <div class="overflow-x-auto rounded-2xl border border-slate-100">
                <table id="userTable" class="w-full min-w-[850px] text-left text-sm">
                    <thead class="bg-slate-50/80 border-b border-slate-200/80">
                        <tr class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Usuario</th>
                            <th class="px-4 py-3">Rol</th>
                            <th class="px-4 py-3">Organización</th>
                            <th class="px-4 py-3">Fecha Alta</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">' . $rows . '</tbody>
                </table>
            </div>
        </div>
        <script>
        function filterUserTable() {
            var input = document.getElementById("userSearchInput");
            var filter = input.value.toLowerCase();
            var rows = document.querySelectorAll(".user-row");
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(filter) > -1 ? "" : "none";
            });
        }
        </script>';
    }

    private static function adminCompaniesView(array $companies): string
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

    private static function adminCreateAdminView(array $users): string
    {
        $admins = array_filter($users, fn($u) => $u['role'] === 'admin');
        $adminRows = '';
        foreach ($admins as $a) {
            $adminRows .= '
            <tr class="border-t border-slate-100">
                <td class="py-3 px-3 text-xs font-mono text-slate-400">#' . $a['id'] . '</td>
                <td class="py-3 px-3">
                    <p class="font-bold text-xs text-slate-800">' . self::e($a['name']) . '</p>
                    <p class="text-[11px] text-slate-500">' . self::e($a['email']) . '</p>
                </td>
                <td class="py-3 px-3 text-xs text-slate-500 font-mono">' . date('Y-m-d', strtotime($a['created_at'])) . '</td>
            </tr>';
        }

        return '
        <div class="grid lg:grid-cols-[1.2fr_0.8fr] gap-8">
            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">
                <div class="pb-5 border-b border-slate-100 mb-6">
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200 mb-1">
                        ' . self::iconShield('w-3.5 h-3.5') . ' Seguridad y Control
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900">Crear Usuario Administrador</h2>
                    <p class="text-xs text-slate-500 mt-1">Registra una nueva cuenta con privilegios administrativos para gestionar el portal.</p>
                </div>
                <form method="post" action="?action=admin_create_admin" class="space-y-4">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nombre Completo del Administrador</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconUser('w-5 h-5') . '</span>
                            <input required name="name" placeholder="Ej: Carlos Mendoza" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Correo Electrónico Institucional</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconMail('w-5 h-5') . '</span>
                            <input required name="email" type="email" placeholder="carlos.admin@yeb.test" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contraseña Inicial</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconLock('w-5 h-5') . '</span>
                            <input required name="password" type="password" placeholder="Mínimo 6 caracteres" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">
                        </div>
                    </div>
                    <div class="pt-3">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] px-6 py-3.5 font-bold text-white shadow-md shadow-blue-900/10 transition hover:bg-[#12365f] text-sm">
                            ' . self::iconCheck('w-4 h-4') . ' Registrar Usuario Administrador
                        </button>
                    </div>
                </form>
            </article>

            <div class="space-y-6">
                <article class="rounded-3xl bg-gradient-to-br from-[#102a43] to-[#1e40af] p-6 text-white shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 mb-4">' . self::iconShield('w-6 h-6 text-white') . '</div>
                    <h3 class="text-lg font-bold">Privilegios Administrativos</h3>
                    <p class="mt-2 text-xs text-white/80 leading-relaxed">Los administradores poseen acceso completo al registro de auditorías, capacidad de eliminar cuentas de usuarios o empresas y supervisión general de la plataforma.</p>
                </article>

                <article class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            ' . self::iconUser('w-4 h-4 text-[#17457c]') . ' Administradores del Sistema
                        </h4>
                        <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">' . count($admins) . ' activos</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <th class="py-1 px-3">ID</th>
                                    <th class="py-1 px-3">Nombre</th>
                                    <th class="py-1 px-3">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>' . $adminRows . '</tbody>
                        </table>
                    </div>
                </article>
            </div>
        </div>';
    }

    private static function companyNav(string $active): string
    {
        $items = [
            'dashboard' => ['label' => 'Resumen', 'icon' => self::iconChart('w-4 h-4')],
            'company_jobs' => ['label' => 'Mis vacantes', 'icon' => self::iconBriefcase('w-4 h-4')],
            'company_applications' => ['label' => 'Candidatos', 'icon' => self::iconUser('w-4 h-4')],
            'company_messages' => ['label' => 'Mensajes', 'icon' => self::iconChat('w-4 h-4')],
            'company_profile' => ['label' => 'Perfil corporativo', 'icon' => self::iconBuilding('w-4 h-4')],
        ];
        $html = '<nav class="profile-tabs company-tabs flex flex-wrap gap-2 p-1.5 rounded-2xl bg-slate-100 mb-8 border border-slate-200/80">';
        foreach ($items as $key => $item) {
            $isActive = ($key === $active);
            $class = $isActive 
                ? 'inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition' 
                : 'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-white/80 transition';
            $html .= '<a class="' . $class . '" href="?action=' . $key . '">' . $item['icon'] . '<span>' . $item['label'] . '</span></a>';
        }
        $html .= '</nav>';
        return $html;
    }

    public static function companyArea(array $user, string $action, ?string $message, ?string $error, CompanyProfileService $companies, JobsController $jobsController, ApplicationsController $applicationsController, ?ChatController $chatController = null): string
    {
        $company = $companies->find($user['company_id']);
        if (!$company) return self::alerts($message, 'No se encontró la empresa asociada a la cuenta.');
        $jobList = $jobsController->byCompany($user['company_id']);
        $applicationList = $applicationsController->byCompany($user['company_id']);
        $profilePhoto = $company['profile_photo_path'] 
            ? '<img src="?action=download_profile_photo&type=company&id=' . $user['company_id'] . '" alt="Logo" class="company-logo h-20 w-20 rounded-2xl object-cover border-2 border-slate-200 shadow-sm">' 
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
            $content .= self::companyJobsView($jobList, $user);
        } elseif ($action === 'company_applications') {
            $content .= self::companyApplicationsView($applicationList, $user);
        } elseif ($action === 'company_messages') {
            $conversations = $chatController ? $chatController->conversations($user) : [];
            $content .= self::companyMessagesView($conversations, $user);
        } else {
            $published = count(array_filter($jobList, fn (array $job): bool => $job['status'] === 'published'));
            $pending = count(array_filter($applicationList, fn (array $item): bool => in_array($item['status'], ['pending', 'review'], true)));

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
                $statusConfig = match($item['status']) {
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

    private static function companyProfileView(array $company, array $user): string
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
        </div>';
    }

    private static function companyJobsView(array $jobs, array $user): string
    {
        $rows = '';
        foreach ($jobs as $job) {
            $closeBtn = $job['status'] === 'published' 
                ? '<form method="post" action="?action=close_job" class="inline">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <input type="hidden" name="job_id" value="' . $job['id'] . '">
                    <button class="text-xs font-semibold text-rose-600 hover:text-rose-700 border border-rose-200 hover:bg-rose-50 px-3 py-1.5 rounded-lg transition" onclick="return confirm(\'¿Estás seguro de cerrar esta vacante?\')">Cerrar vacante</button>
                </form>' 
                : '';

            $statusConfig = match($job['status']) {
                'published' => ['label' => 'Publicada', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                'draft' => ['label' => 'Borrador', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                default => ['label' => 'Cerrada', 'class' => 'bg-slate-100 text-slate-600 border-slate-200']
            };

            $modePill = match(strtolower($job['work_mode'])) {
                'remote', 'remoto' => '<span class="rounded-md bg-purple-50 text-purple-700 px-2 py-0.5 text-xs font-medium border border-purple-200">Remoto</span>',
                'hybrid', 'híbrido' => '<span class="rounded-md bg-blue-50 text-blue-700 px-2 py-0.5 text-xs font-medium border border-blue-200">Híbrido</span>',
                default => '<span class="rounded-md bg-teal-50 text-teal-700 px-2 py-0.5 text-xs font-medium border border-teal-200">Presencial</span>'
            };

            $salaryText = ($job['salary_min'] || $job['salary_max']) 
                ? '$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.')
                : 'Salario a convenir';

            $rows .= '
            <article class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-slate-300">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-bold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span>
                            ' . $modePill . '
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">' . self::e($job['title']) . '</h3>
                        <p class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                            <span>' . self::iconLocation('w-3.5 h-3.5 inline text-slate-400') . ' ' . self::e($job['city']) . '</span>
                            <span>·</span>
                            <span>' . self::e($job['experience']) . '</span>
                            <span>·</span>
                            <span class="font-semibold text-slate-700">' . $salaryText . '</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3 self-end sm:self-center">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-700">
                            ' . self::iconUser('w-3.5 h-3.5 text-slate-500') . ' ' . $job['applications_count'] . ' candidatos
                        </span>
                        ' . $closeBtn . '
                    </div>
                </div>
            </article>';
        }

        return '
        <div class="grid lg:grid-cols-[1fr_1.1fr] gap-8" id="crear">
            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">
                <div class="pb-5 border-b border-slate-100 mb-6">
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200 mb-1">
                        ' . self::iconPlus('w-3.5 h-3.5') . ' Nueva Oferta
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900">Crear una Vacante</h2>
                    <p class="text-xs text-slate-500 mt-1">Publica una oportunidad atractiva para que candidatos jóvenes puedan postularse.</p>
                </div>
                <form method="post" action="?action=create_job" class="space-y-4">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    ' . self::field('title', 'Título del cargo o posición', 'text', 'Ej: Frontend Developer Junior') . '
                    <div class="grid grid-cols-2 gap-4">
                        ' . self::field('city', 'Ciudad de ubicación', 'text', 'Ej: Bogotá, Medellín o Remoto') . '
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Modalidad de trabajo</label>
                            <select name="work_mode" class="w-full rounded-xl border border-slate-200 p-3 text-sm text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent">
                                <option value="hybrid">Híbrido</option>
                                <option value="remote">Remoto</option>
                                <option value="onsite">Presencial</option>
                            </select>
                        </div>
                    </div>
                    ' . self::field('experience', 'Experiencia requerida', 'text', 'Ej: Sin experiencia, 0 - 1 año o Pasantía') . '
                    <div class="grid grid-cols-2 gap-4">
                        ' . self::field('salary_min', 'Salario mínimo ($)', 'number', 'Ej: 2500000') . '
                        ' . self::field('salary_max', 'Salario máximo ($)', 'number', 'Ej: 3500000') . '
                    </div>
                    ' . self::field('description', 'Descripción del puesto y responsabilidades', 'textarea', 'Describe las responsabilidades principales, competencias valoradas y oportunidades de aprendizaje...') . '
                    <div class="pt-2">
                        <button class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#12365f]">
                            ' . self::iconPlus('w-4 h-4') . ' Publicar vacante en el portal
                        </button>
                    </div>
                </form>
            </article>

            <article class="space-y-4">
                <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Mis Vacantes</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Vacantes publicadas por tu empresa</p>
                        </div>
                        <span class="rounded-xl bg-slate-100 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-700">' . count($jobs) . ' registradas</span>
                    </div>
                    <div class="space-y-3">' . ($rows ?: '<div class="py-12 text-center text-sm text-slate-400">Aún no has creado vacantes. Utiliza el formulario a la izquierda para publicar tu primera oferta.</div>') . '</div>
                </div>
            </article>
        </div>';
    }

    private static function companyApplicationsView(array $applications, array $user): string
    {
        $rows = '';
        foreach ($applications as $item) {
            $cv = $item['cv_original_name'] 
                ? '<a class="inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline bg-blue-50 border border-blue-200 px-3 py-1 rounded-lg" href="?action=download_cv&candidate=' . $item['candidate_id'] . '">' . self::iconDownload('w-3.5 h-3.5') . ' Descargar Hoja de Vida</a>' 
                : '<span class="text-xs text-slate-400 font-medium bg-slate-100 px-2.5 py-1 rounded-lg">Sin CV adjunto</span>';

            $statusOptions = '';
            foreach (['pending' => 'Pendiente', 'review' => 'En revisión', 'interview' => 'Entrevista', 'accepted' => 'Aceptado', 'rejected' => 'Rechazado'] as $val => $label) {
                $statusOptions .= '<option value="' . $val . '"' . ($item['status'] === $val ? ' selected' : '') . '>' . $label . '</option>';
            }

            $currentBadge = match($item['status']) {
                'accepted' => '<span class="rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold">Aceptado</span>',
                'interview' => '<span class="rounded-full bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-0.5 text-xs font-bold">Entrevista</span>',
                'review' => '<span class="rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 text-xs font-bold">En revisión</span>',
                'rejected' => '<span class="rounded-full bg-rose-50 text-rose-700 border border-rose-200 px-2.5 py-0.5 text-xs font-bold">Rechazado</span>',
                default => '<span class="rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 text-xs font-bold">Pendiente</span>'
            };

            $rows .= '
            <article class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-slate-300">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-extrabold text-base border border-blue-100">' . strtoupper(substr($item['candidate_name'], 0, 1)) . '</div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900">' . self::e($item['candidate_name']) . '</h3>
                                ' . $currentBadge . '
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1">' . self::iconMail('w-3.5 h-3.5 text-slate-400') . ' ' . self::e($item['email']) . '</span>
                                <span>·</span>
                                <span class="font-semibold text-slate-700">' . self::e($item['title']) . '</span>
                            </p>
                            <div class="mt-2.5">' . $cv . '</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 self-end md:self-center">
                        <a href="?action=chat&application_id=' . $item['id'] . '" class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50/80 hover:bg-blue-100 text-[#17457c] px-3.5 py-2 text-xs font-bold transition shadow-sm">
                            ' . self::iconChat('w-3.5 h-3.5') . ' Mensajes
                        </a>
                        <form method="post" action="?action=update_application" class="flex items-center gap-2 bg-slate-50 border border-slate-200 p-1.5 rounded-xl">
                            <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                            <input type="hidden" name="application_id" value="' . $item['id'] . '">
                            <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#17457c]">' . $statusOptions . '</select>
                            <button class="inline-flex items-center gap-1 rounded-lg bg-[#17457c] px-3 py-1.5 text-xs font-bold text-white transition hover:bg-[#12365f]">
                                ' . self::iconCheck('w-3.5 h-3.5') . ' Actualizar
                            </button>
                        </form>
                    </div>
                </div>
            </article>';
        }

        return '
        <div class="max-w-5xl mx-auto">
            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 mb-6">
                    <div>
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200 mb-1">
                            ' . self::iconUser('w-3.5 h-3.5') . ' Postulantes
                        </div>
                        <h2 class="text-2xl font-bold text-slate-900">Candidatos Registrados</h2>
                        <p class="text-xs text-slate-500 mt-1">Revisa perfiles, descarga hojas de vida y actualiza el estado de cada postulación.</p>
                    </div>
                    <span class="rounded-xl bg-slate-100 border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 self-start sm:self-auto">' . count($applications) . ' postulaciones en total</span>
                </div>
                <div class="space-y-4">' . ($rows ?: '<div class="py-16 text-center text-sm text-slate-400">Aún no se registran postulaciones para tus vacantes.</div>') . '</div>
            </article>
        </div>';
    }

    private static function companyMessagesView(array $conversations, array $user): string
    {
        $cards = '';
        foreach ($conversations as $c) {
            $statusBadge = match($c['status']) {
                'accepted' => '<span class="rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold">Aceptado</span>',
                'interview' => '<span class="rounded-full bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-0.5 text-xs font-bold">Entrevista</span>',
                'review' => '<span class="rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 text-xs font-bold">En revisión</span>',
                'rejected' => '<span class="rounded-full bg-rose-50 text-rose-700 border border-rose-200 px-2.5 py-0.5 text-xs font-bold">Rechazado</span>',
                default => '<span class="rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 text-xs font-bold">Pendiente</span>'
            };

            $lastMsg = $c['last_message'] 
                ? self::e(mb_strimwidth($c['last_message'], 0, 80, '...')) 
                : '<span class="italic text-slate-400">Sin mensajes todavía. ¡Inicia el chat con este candidato!</span>';

            $unreadBadge = (!empty($c['unread_count']) && (int) $c['unread_count'] > 0)
                ? '<span class="rounded-full bg-blue-600 text-white text-[10px] font-bold px-2 py-0.5">' . (int) $c['unread_count'] . ' nuevo(s)</span>'
                : '';

            $timeStr = $c['last_message_at'] ? date('d/m/Y H:i', strtotime($c['last_message_at'])) : date('d/m/Y', strtotime($c['applied_at']));

            $cards .= '
            <a href="?action=chat&application_id=' . $c['application_id'] . '" class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 rounded-2xl border border-slate-200/90 bg-white hover:border-[#17457c] hover:shadow-md transition group">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-bold text-lg border border-blue-100">
                        ' . strtoupper(substr($c['candidate_name'], 0, 1)) . '
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-bold text-base text-slate-900 group-hover:text-[#17457c] transition">' . self::e($c['candidate_name']) . '</h3>
                            ' . $statusBadge . '
                            ' . $unreadBadge . '
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                            <span>' . self::e($c['candidate_email']) . '</span>
                            <span>·</span>
                            <span class="font-semibold text-slate-700">Vacante: ' . self::e($c['job_title']) . '</span>
                        </p>
                        <p class="text-xs text-slate-600 mt-2 line-clamp-1">' . $lastMsg . '</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-center md:items-end justify-between gap-2 text-right">
                    <span class="text-[11px] text-slate-400">' . $timeStr . '</span>
                    <span class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] group-hover:translate-x-0.5 transition">
                        Abrir chat ' . self::iconArrowRight('w-3.5 h-3.5') . '
                    </span>
                </div>
            </a>';
        }

        return '
        <div class="max-w-5xl mx-auto">
            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 mb-6">
                    <div>
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200 mb-1">
                            ' . self::iconChat('w-3.5 h-3.5') . ' Mensajería
                        </div>
                        <h2 class="text-2xl font-bold text-slate-900">Conversaciones con Candidatos</h2>
                        <p class="text-xs text-slate-500 mt-1">Comunícate en tiempo real con los postulantes a tus vacantes activas.</p>
                    </div>
                    <span class="rounded-xl bg-slate-100 border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 self-start sm:self-auto">' . count($conversations) . ' conversaciones</span>
                </div>
                <div class="space-y-3.5">' . ($cards ?: '<div class="py-16 text-center text-sm text-slate-400">No hay postulaciones ni conversaciones registradas aún.</div>') . '</div>
            </article>
        </div>';
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
            $jobCards .= '
            <a href="?action=dashboard&q=' . rawurlencode($_GET['q'] ?? '') . '&location=' . rawurlencode($_GET['location'] ?? '') . '&job=' . $job['id'] . '" class="block border-b border-slate-200 p-5 transition ' . ($isSelected ? 'border-l-4 border-l-[#17457c] bg-white shadow-sm' : 'bg-white hover:bg-slate-50') . '">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-[#17457c]">Oportunidad</p>
                        <h3 class="mt-1 text-base font-bold text-slate-900">' . self::e($job['title']) . '</h3>
                        <p class="mt-1 text-sm font-medium text-slate-600">' . self::e($job['company_name']) . '</p>
                    </div>
                    <span class="p-1">' . self::iconHeart($isSaved, 'w-5 h-5') . '</span>
                </div>
                <p class="mt-2.5 text-xs text-slate-500 flex items-center gap-2">
                    <span>' . self::iconLocation('w-3.5 h-3.5 inline text-slate-400') . ' ' . self::e($job['city']) . '</span>
                    <span>·</span>
                    <span>' . self::e($job['work_mode']) . '</span>
                </p>
                <div class="mt-3 flex items-center justify-between text-xs text-slate-400">
                    <span>' . self::e($job['experience']) . '</span>
                    <span>' . date('d M', strtotime($job['created_at'])) . '</span>
                </div>
            </a>';
        }

        $detail = $selected ? '
        <div class="flex items-start justify-between gap-5">
            <div>
                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold uppercase tracking-wider text-[#17457c] border border-blue-200">Oportunidad seleccionada</span>
                <h1 class="mt-2 text-2xl lg:text-3xl font-extrabold text-slate-900">' . self::e($selected['title']) . '</h1>
                <p class="mt-1.5 text-base font-semibold text-[#17457c]">' . self::e($selected['company_name']) . '</p>
                <p class="mt-1 text-sm text-slate-500">' . self::iconLocation('w-4 h-4 inline text-slate-400') . ' ' . self::e($selected['city']) . '</p>
            </div>
            <div class="hidden h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-[#17457c] sm:flex">' . self::iconBriefcase('w-7 h-7') . '</div>
        </div>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <form method="post" action="?action=apply">
                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                <input type="hidden" name="job_id" value="' . $selected['id'] . '">
                <button class="rounded-xl bg-[#17457c] px-7 py-2.5 font-bold text-white shadow-sm transition hover:bg-[#12365f]">Aplicar a la vacante</button>
            </form>
            <form method="post" action="?action=toggle_saved_job">
                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                <input type="hidden" name="return_to" value="' . self::e('?action=search&q=' . rawurlencode($_GET['q'] ?? '') . '&location=' . rawurlencode($_GET['location'] ?? '') . '&job=' . (string) $selected['id']) . '">
                <input type="hidden" name="job_id" value="' . $selected['id'] . '">
                <button class="circle-action" title="Guardar vacante">' . self::iconHeart(in_array((int) $selected['id'], $savedIds, true), 'w-5 h-5') . '</button>
            </form>
            <button class="circle-action" title="Copiar enlace" onclick="navigator.clipboard?.writeText(window.location.href);alert(\'Enlace copiado al portapapeles\');return false">' . self::iconShare('w-5 h-5 text-[#17457c]') . '</button>
            <form method="post" action="?action=hide_job">
                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                <input type="hidden" name="return_to" value="' . self::e('?action=search&q=' . rawurlencode($_GET['q'] ?? '') . '&location=' . rawurlencode($_GET['location'] ?? '') . '&job=' . (string) $selected['id']) . '">
                <input type="hidden" name="job_id" value="' . $selected['id'] . '">
                <button class="circle-action" title="Ocultar vacante">' . self::iconEyeOff('w-5 h-5 text-slate-500') . '</button>
            </form>
        </div>
        <div class="my-6 border-t border-slate-200"></div>
        <div class="grid gap-4 text-sm text-slate-700 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">
                <div class="flex items-center gap-2 text-slate-500">' . self::iconBriefcase('w-4 h-4') . '<span class="text-xs">Modalidad</span></div>
                <p class="mt-1 font-semibold text-slate-800">' . self::e($selected['work_mode']) . '</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">
                <div class="flex items-center gap-2 text-slate-500">' . self::iconClock('w-4 h-4') . '<span class="text-xs">Experiencia</span></div>
                <p class="mt-1 font-semibold text-slate-800">' . self::e($selected['experience']) . '</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">
                <div class="flex items-center gap-2 text-slate-500">' . self::iconCurrency('w-4 h-4') . '<span class="text-xs">Rango Salarial</span></div>
                <p class="mt-1 font-semibold text-slate-800">$' . number_format((float) $selected['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $selected['salary_max'], 0, ',', '.') . '</p>
            </div>
        </div>
        <div class="mt-7 border-t border-slate-200 pt-6">
            <h2 class="text-lg font-bold text-slate-900">Descripción de la oferta</h2>
            <p class="mt-3.5 whitespace-pre-line leading-7 text-slate-600 text-sm">' . self::e($selected['description']) . '</p>
        </div>' : '<div class="flex h-full items-center justify-center p-12 text-center text-slate-400">No se encontraron vacantes con los criterios de búsqueda.</div>';

        return $html . '
        <section class="dashboard-shell -mx-5 min-h-[calc(100vh-78px)] bg-[#f4f7fb] md:-mx-8">
            <div class="border-b border-slate-200 bg-white px-5 py-4 md:px-8">
                <form method="get" class="mx-auto flex max-w-7xl flex-col gap-3 md:flex-row">
                    <input type="hidden" name="action" value="dashboard">
                    <div class="search-field flex-1 flex items-center gap-2.5 rounded-full border border-slate-200 bg-white px-4 py-2">
                        <span class="text-slate-400">' . self::iconSearch('w-4 h-4') . '</span>
                        <input name="q" value="' . $keyword . '" placeholder="Cargo o palabra clave" class="w-full text-sm outline-none">
                    </div>
                    <div class="search-field flex-1 flex items-center gap-2.5 rounded-full border border-slate-200 bg-white px-4 py-2">
                        <span class="text-slate-400">' . self::iconLocation('w-4 h-4') . '</span>
                        <input name="location" value="' . $location . '" placeholder="Ciudad o modalidad" class="w-full text-sm outline-none">
                    </div>
                    <button class="rounded-full bg-[#17457c] px-6 py-2.5 text-sm font-bold text-white transition hover:bg-[#12365f]">Buscar empleos</button>
                </form>
                <div class="mx-auto mt-3 flex max-w-7xl gap-2 overflow-x-auto pb-1 text-xs">
                    <a href="?action=dashboard" class="filter-pill">Recientes</a>
                    <a href="?action=dashboard&location=Remote" class="filter-pill">Remoto</a>
                    <a href="?action=dashboard&location=Hybrid" class="filter-pill">Híbrido</a>
                    <a href="?action=dashboard&location=Presencial" class="filter-pill">Presencial</a>
                </div>
            </div>
            <div class="mx-auto grid max-w-7xl lg:grid-cols-[minmax(330px,38%)_1fr]">
                <aside class="max-h-[calc(100vh-210px)] overflow-y-auto border-r border-slate-200 bg-white">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-700"><strong>' . count($jobs) . '</strong> ofertas disponibles</p>
                    </div>
                    ' . $jobCards . '
                </aside>
                <article class="min-h-[calc(100vh-210px)] bg-white p-6 md:p-8">' . $detail . '</article>
            </div>
        </section>';
    }

    private static function candidateNav(string $active): string
    {
        $items = ['dashboard' => 'Mi área', 'profile' => 'Hoja de Vida', 'applications' => 'Mis aplicaciones', 'messages' => 'Mensajes', 'alerts' => 'Mis alertas', 'favorites' => 'Mis favoritos'];
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
        foreach (array_slice($jobs, 0, 4) as $job) {
            $recommendations .= '
            <a href="?action=search&job=' . $job['id'] . '" class="profile-job">
                <p class="text-xs font-bold uppercase tracking-wider text-[#17457c]">Oportunidad recomendada</p>
                <h3 class="font-bold text-slate-900">' . self::e($job['title']) . '</h3>
                <p class="text-slate-500">' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p>
                <span class="font-bold text-[#17457c]">$' . number_format((float) $job['salary_min'], 0, ',', '.') . '</span>
            </a>';
        }
        $photo = $profile['profile_photo_path'] 
            ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto de perfil" class="profile-avatar">' 
            : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';

        return self::alerts($message, $error) . '
        <section class="candidate-page">
            ' . self::candidateNav('dashboard') . '
            <div class="profile-grid">
                <div>
                    <article class="profile-card">
                        <div class="flex items-center gap-5">
                            ' . $photo . '
                            <div>
                                <h1>' . self::e($profile['name']) . '</h1>
                                <p>' . self::e($profile['professional_title'] ?: 'Completa tu titular profesional') . '</p>
                                <p class="profile-completion">Perfil completado <strong>' . $completed . '%</strong></p>
                            </div>
                            <a class="ml-auto text-xl text-slate-400 hover:text-[#17457c]" href="?action=profile">' . self::iconArrowRight('w-5 h-5') . '</a>
                        </div>
                        <div class="progress-track"><span style="width:' . $completed . '%"></span></div>
                        <div class="mt-6 flex justify-between text-sm text-slate-500">
                            <span>Tu perfil profesional</span>
                            <a class="font-bold text-[#17457c]" href="?action=profile">Editar perfil</a>
                        </div>
                    </article>
                    <article class="profile-card mt-6">
                        <h2>Mis aplicaciones</h2>
                        <div class="stats-grid">
                            <div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-500"></span><span>Postulado</span><strong>' . ($counts['pending'] + $counts['review']) . '</strong></div>
                            <div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-500"></span><span>En proceso</span><strong>' . $counts['interview'] . '</strong></div>
                            <div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span><span>Finalista</span><strong>' . $counts['accepted'] . '</strong></div>
                            <div class="rounded-xl border border-slate-100 p-3 bg-slate-50/70"><span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-400"></span><span>Finalizadas</span><strong>' . $counts['rejected'] . '</strong></div>
                        </div>
                        <a href="?action=applications" class="profile-link flex items-center gap-1">Ver mis aplicaciones ' . self::iconArrowRight('w-4 h-4') . '</a>
                    </article>
                    <article class="profile-card mt-6">
                        <h2>Accesos rápidos</h2>
                        <div class="quick-links">
                            <a href="?action=profile"><span>Editar hoja de vida</span> ' . self::iconArrowRight('w-4 h-4 text-slate-400') . '</a>
                            <a href="?action=messages"><span>Mensajes con empresas</span> ' . self::iconArrowRight('w-4 h-4 text-slate-400') . '</a>
                            <a href="?action=favorites"><span>Ofertas favoritas</span> ' . self::iconArrowRight('w-4 h-4 text-slate-400') . '</a>
                            <a href="?action=alerts"><span>Configurar alertas</span> ' . self::iconArrowRight('w-4 h-4 text-slate-400') . '</a>
                            <a href="?action=search"><span>Buscar empleos</span> ' . self::iconArrowRight('w-4 h-4 text-slate-400') . '</a>
                        </div>
                    </article>
                </div>
                <div>
                    <article class="profile-card recommendations">
                        <h2>Descubre estas <strong>ofertas que te pueden interesar</strong></h2>
                        ' . ($recommendations ?: '<p class="p-6 text-slate-500">Aún no hay recomendaciones disponibles.</p>') . '
                    </article>
                    <article class="premium-card mt-6">
                        <h2>Potencia tu búsqueda de empleo</h2>
                        <p>Completa tu perfil y mantén tu hoja de vida actualizada para destacar ante las empresas.</p>
                        <a href="?action=profile">Completar perfil</a>
                    </article>
                </div>
            </div>
        </section>';
    }

    public static function candidateProfile(array $user, ?string $message, ?string $error, UserController $userController): string
    {
        $profile = $userController->profile($user['id']);
        if (!$profile) return self::alerts($message, 'No se encontró el perfil del usuario.');
        $cv = $profile['cv_original_name'] 
            ? '<div class="flex items-center gap-2 text-emerald-700 font-medium text-sm">' . self::iconCheck('w-4 h-4') . '<span>' . self::e($profile['cv_original_name']) . '</span> · <a class="font-bold underline" href="?action=download_cv&candidate=' . $user['id'] . '">Descargar</a> · <form method="post" action="?action=delete_cv" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><button class="font-bold text-rose-600 hover:underline" onclick="return confirm(\'¿Eliminar tu hoja de vida?\')">Eliminar</button></form></div>' 
            : '<p class="text-slate-500 text-sm">No has cargado una hoja de vida.</p>';
        $photo = $profile['profile_photo_path'] 
            ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto" class="profile-avatar">' 
            : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';
        $form = '<form method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre completo', $profile['name']) . self::fieldValue('professional_title', 'Titular profesional', $profile['professional_title']) . self::fieldValue('phone', 'Teléfono', $profile['phone']) . self::fieldValue('location', 'Ubicación', $profile['location']) . self::fieldValue('availability', 'Disponibilidad', $profile['availability']) . self::fieldValue('linkedin_url', 'LinkedIn', $profile['linkedin_url'], 'url') . self::fieldValue('portfolio_url', 'Portafolio', $profile['portfolio_url'], 'url') . self::fieldValue('skills', 'Conocimientos y habilidades', $profile['skills'], 'textarea') . self::fieldValue('experience', 'Experiencia profesional', $profile['experience'], 'textarea') . self::fieldValue('education', 'Educación', $profile['education'], 'textarea') . self::fieldValue('bio', 'Resumen profesional', $profile['bio'], 'textarea') . '<label>Foto de perfil<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label><button class="primary-button">Guardar cambios</button></form>';
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('profile') . '<h1 class="page-title">Hoja de Vida</h1><div class="profile-layout"><div><article class="profile-card profile-identity"><div class="flex items-center gap-5">' . $photo . '<div><h2>' . self::e($profile['name']) . '</h2><p>' . self::e($profile['location'] ?: 'Agrega tu ubicación') . '</p><p>' . self::e($profile['phone'] ?: 'Agrega tu teléfono') . '</p></div></div><div class="mt-8">' . $form . '</div></article><article class="profile-card mt-6"><h2>Documentos adjuntos</h2><div class="mt-4">' . $cv . '</div><form method="post" action="?action=upload_cv" enctype="multipart/form-data" class="mt-5 flex gap-3"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required type="file" name="cv" accept=".pdf,.doc,.docx" class="flex-1 rounded-lg border border-slate-200 p-2.5 text-sm"><button class="primary-button !py-2.5">Subir CV</button></form></article></div><aside class="profile-card side-card"><h2>Tu perfil profesional</h2><p class="mt-2 text-sm text-slate-500">Una información completa y detallada aumenta las posibilidades de ser contactado por reclutadores.</p><a href="?action=dashboard" class="mt-4 inline-flex items-center gap-1 font-bold text-sm text-[#17457c]">Volver a Mi área ' . self::iconArrowRight('w-3.5 h-3.5') . '</a></aside></div></section>';
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
            $rows .= '
            <article class="application-card flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5">
                <div>
                    <h2 class="text-base font-bold text-slate-900">' . self::e($item['title']) . '</h2>
                    <p class="text-xs text-slate-500 mt-0.5">' . self::e($item['company_name']) . ' · ' . self::e($item['city']) . '</p>
                </div>
                <div class="flex items-center gap-2.5 self-end sm:self-center">
                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span>
                    <a href="?action=chat&application_id=' . $item['id'] . '" class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50/80 hover:bg-blue-100 text-[#17457c] px-3.5 py-1.5 text-xs font-bold transition shadow-sm">
                        ' . self::iconChat('w-3.5 h-3.5') . ' Mensajes
                    </a>
                </div>
            </article>';
        }
        return self::alerts($message, $error) . '<section class="candidate-page">' . self::candidateNav('applications') . '<h1 class="page-title">Mis aplicaciones</h1><div class="application-list space-y-3">' . ($rows ?: '<div class="profile-card p-8 text-center text-slate-500">Aún no tienes aplicaciones registradas.</div>') . '</div></section>';
    }

    public static function candidateMessages(array $user, array $conversations, ?string $message, ?string $error): string
    {
        $cards = '';
        foreach ($conversations as $c) {
            $statusBadge = match($c['status']) {
                'accepted' => '<span class="rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold">Aceptado</span>',
                'interview' => '<span class="rounded-full bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-0.5 text-xs font-bold">Entrevista</span>',
                'review' => '<span class="rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 text-xs font-bold">En revisión</span>',
                'rejected' => '<span class="rounded-full bg-rose-50 text-rose-700 border border-rose-200 px-2.5 py-0.5 text-xs font-bold">Rechazado</span>',
                default => '<span class="rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 text-xs font-bold">Pendiente</span>'
            };

            $lastMsg = $c['last_message'] 
                ? self::e(mb_strimwidth($c['last_message'], 0, 80, '...')) 
                : '<span class="italic text-slate-400">Sin mensajes todavía. ¡Haz clic para iniciar la conversación!</span>';

            $unreadBadge = (!empty($c['unread_count']) && (int) $c['unread_count'] > 0)
                ? '<span class="rounded-full bg-blue-600 text-white text-[10px] font-bold px-2 py-0.5">' . (int) $c['unread_count'] . ' nuevo(s)</span>'
                : '';

            $timeStr = $c['last_message_at'] ? date('d/m/Y H:i', strtotime($c['last_message_at'])) : date('d/m/Y', strtotime($c['applied_at']));

            $cards .= '
            <a href="?action=chat&application_id=' . $c['application_id'] . '" class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 rounded-2xl border border-slate-200/90 bg-white hover:border-[#17457c] hover:shadow-md transition group">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-bold text-lg border border-blue-100">
                        ' . strtoupper(substr($c['company_name'], 0, 1)) . '
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-bold text-base text-slate-900 group-hover:text-[#17457c] transition">' . self::e($c['company_name']) . '</h3>
                            ' . $statusBadge . '
                            ' . $unreadBadge . '
                        </div>
                        <p class="text-xs font-medium text-slate-500 mt-0.5">Vacante: <span class="font-bold text-slate-700">' . self::e($c['job_title']) . '</span></p>
                        <p class="text-xs text-slate-600 mt-2 line-clamp-1">' . $lastMsg . '</p>
                    </div>
                </div>
                <div class="flex md:flex-col items-center md:items-end justify-between gap-2 text-right">
                    <span class="text-[11px] text-slate-400">' . $timeStr . '</span>
                    <span class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] group-hover:translate-x-0.5 transition">
                        Ver chat ' . self::iconArrowRight('w-3.5 h-3.5') . '
                    </span>
                </div>
            </a>';
        }

        return self::alerts($message, $error) . '
        <section class="candidate-page">
            ' . self::candidateNav('messages') . '
            <div class="max-w-4xl mx-auto">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">Mensajes con Empresas</h1>
                        <p class="text-xs text-slate-500 mt-1">Comunícate directamente con los empleadores de las ofertas a las que te has postulado.</p>
                    </div>
                    <span class="rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-700">' . count($conversations) . ' conversación(es)</span>
                </div>
                <div class="space-y-3.5">' . ($cards ?: '<div class="profile-card p-12 text-center text-slate-400">No tienes conversaciones activas aún. Aplica a ofertas de empleo para poder chatear con las empresas.</div>') . '</div>
            </div>
        </section>';
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

    public static function home(array $jobs, ?array $user, string $action, ?string $message, ?string $error, string $cedulaProvider = 'sandbox', array $cedulaSamples = []): string
    {
        $alerts = self::alerts($message, $error);

        // LOGIN VIEW
        if ($action === 'login') {
            return $alerts . '
            <div class="min-h-[calc(100vh-220px)] flex items-center justify-center py-10 px-4">
                <div class="w-full max-w-md">
                    <section class="rounded-3xl border border-slate-200/90 bg-white p-8 sm:p-10 shadow-xl shadow-slate-200/50">
                        <div class="text-center mb-8">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white shadow-md shadow-blue-900/20">
                                ' . self::iconLock('w-7 h-7 text-white') . '
                            </div>
                            <h1 class="display text-3xl font-extrabold text-slate-900">Iniciar Sesión</h1>
                            <p class="mt-2 text-sm text-slate-500">Ingresa a tu cuenta de candidato, empresa o administración.</p>
                        </div>

                        <form method="post" action="?action=login" class="space-y-4" id="loginForm">
                            <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Correo Electrónico</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconMail('w-5 h-5') . '</span>
                                    <input required id="loginEmail" name="email" type="email" placeholder="tu.correo@ejemplo.com" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Contraseña</label>
                                    <span class="text-xs text-slate-400">Protección SSL</span>
                                </div>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconLock('w-5 h-5') . '</span>
                                    <input required id="loginPassword" name="password" type="password" placeholder="••••••••" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                                </div>
                            </div>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] p-3.5 font-bold text-white shadow-md shadow-blue-900/10 transition hover:bg-[#12365f] text-sm">
                                <span>Ingresar al Portal</span> ' . self::iconArrowRight('w-4 h-4') . '
                            </button>
                        </form>

                        <div class="mt-6 pt-5 border-t border-slate-100">
                            <p class="text-center text-sm text-slate-500">
                                ¿No tienes cuenta todavía?
                                <a href="?action=register" class="font-bold text-[#17457c] hover:underline">Crear cuenta gratis</a>
                            </p>
                        </div>

                        <!-- Cuentas de demostración -->
                        <div class="mt-6 rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#17457c] mb-2 flex items-center gap-1.5">
                                ' . self::iconShield('w-3.5 h-3.5') . ' Cuentas Demo de Prueba
                            </p>
                            <div class="grid grid-cols-3 gap-1.5 text-center text-xs">
                                <button type="button" onclick="fillLogin(\'ana@yeb.test\', \'password\')" class="rounded-lg bg-white border border-blue-200 p-2 hover:bg-blue-100/50 transition">
                                    <strong class="block text-slate-800">Candidata</strong>
                                    <span class="text-[10px] text-slate-500">ana@yeb</span>
                                </button>
                                <button type="button" onclick="fillLogin(\'laura@talentolab.test\', \'password\')" class="rounded-lg bg-white border border-blue-200 p-2 hover:bg-blue-100/50 transition">
                                    <strong class="block text-slate-800">Empresa</strong>
                                    <span class="text-[10px] text-slate-500">laura@lab</span>
                                </button>
                                <button type="button" onclick="fillLogin(\'admin@yeb.test\', \'password\')" class="rounded-lg bg-white border border-blue-200 p-2 hover:bg-blue-100/50 transition">
                                    <strong class="block text-slate-800">Admin</strong>
                                    <span class="text-[10px] text-slate-500">admin@yeb</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-2 text-center">Haz clic en una cuenta para rellenar automáticamente las credenciales.</p>
                        </div>
                        <script>
                        function fillLogin(email, pass) {
                            document.getElementById("loginEmail").value = email;
                            document.getElementById("loginPassword").value = pass;
                        }
                        </script>
                    </section>
                </div>
            </div>';
        }

        // REGISTER VIEWS (Candidate & Company)
        if ($action === 'register' || $action === 'register_company') {
            $isCompany = ($action === 'register_company');
            $old = $_SESSION['old_input'] ?? [];
            $maxBirthDate = date('Y-m-d');

            $tabs = '
            <div class="mb-6 flex rounded-2xl bg-slate-100 p-1.5 border border-slate-200/80">
                <a href="?action=register" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl py-2.5 text-center text-xs font-bold transition ' . (!$isCompany ? 'bg-[#17457c] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900') . '">
                    ' . self::iconUser('w-4 h-4') . ' Soy Candidato
                </a>
                <a href="?action=register_company" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl py-2.5 text-center text-xs font-bold transition ' . ($isCompany ? 'bg-[#17457c] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900') . '">
                    ' . self::iconBuilding('w-4 h-4') . ' Soy Empresa
                </a>
            </div>';

            // Aviso visible cuando la validación de cédulas corre contra el simulador local.
            // Solo se listan las cédulas de prueba que todavía no tienen cuenta registrada.
            $sandboxHint = '';
            if ($cedulaProvider === 'sandbox') {
                $rows = '';
                foreach ($cedulaSamples as $document => $record) {
                    $rows .= '<li><span class="font-semibold">' . self::e($document) . '</span> &middot; '
                        . self::e($record['nombre']) . ' &middot; ' . self::e($record['nacimiento']) . '</li>';
                }

                $detail = $rows !== ''
                    ? 'Usa una de estas cédulas ficticias que todavía están libres:<ul class="mt-1 list-disc pl-4 space-y-0.5">' . $rows . '</ul>'
                    : 'Todas las cédulas de prueba ya tienen una cuenta registrada. Elimina el usuario desde el panel de administración para liberar su cédula.';

                $sandboxHint = '
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900 flex items-start gap-2">
                    ' . self::iconAlert('w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5') . '
                    <span><strong>Modo simulación.</strong> La cédula se valida contra un registro local de prueba (sin costo).
                        ' . $detail . '
                    </span>
                </div>';
            }

            if (!$isCompany) {
                // Candidato
                $formContent = '
                <div class="text-center mb-6">
                    <h1 class="display text-3xl font-extrabold text-slate-900">Crear Perfil de Candidato</h1>
                    <p class="mt-2 text-sm text-slate-500">Regístrate en menos de dos minutos y empieza a postularte a vacantes verificadas.</p>
                </div>
                ' . $tabs . '
                <form method="post" action="?action=register" class="space-y-4">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nombre Completo</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconUser('w-5 h-5') . '</span>
                            <input required name="name" value="' . self::e($old['name'] ?? '') . '" placeholder="Ej: Ana María Gómez" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Cédula de Ciudadanía</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconShield('w-5 h-5') . '</span>
                            <input required name="cedula" value="' . self::e($old['cedula'] ?? '') . '" inputmode="numeric" pattern="[0-9.\s]{6,20}" placeholder="Ej: 1.020.203.040" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Fecha de nacimiento</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconCalendar('w-5 h-5') . '</span>
                            <input required name="birth_date" type="date" max="' . self::e($maxBirthDate) . '" value="' . self::e($old['birth_date'] ?? '') . '" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Correo Electrónico</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconMail('w-5 h-5') . '</span>
                            <input required name="email" type="email" value="' . self::e($old['email'] ?? '') . '" placeholder="ana@ejemplo.com" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contraseña</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconLock('w-5 h-5') . '</span>
                            <input required name="password" type="password" placeholder="Mínimo 6 caracteres" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div class="rounded-xl bg-blue-50 border border-blue-200 p-3 text-xs text-blue-900 flex items-start gap-2">
                        ' . self::iconShield('w-4 h-4 text-blue-700 flex-shrink-0 mt-0.5') . '
                        <span>Validamos tu cédula contra el registro civil y verificamos que tus datos coincidan. El registro es solo para jóvenes de hasta 24 años.</span>
                    </div>
                    ' . $sandboxHint . '
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600 flex items-start gap-2">
                        ' . self::iconCheck('w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5') . '
                        <span>Acceso 100% gratuito, postulaciones directas y alertas de empleo.</span>
                    </div>
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] p-3.5 font-bold text-white shadow-md shadow-blue-900/10 transition hover:bg-[#12365f] text-sm">
                        <span>Crear Cuenta de Candidato</span> ' . self::iconArrowRight('w-4 h-4') . '
                    </button>
                </form>';
            } else {
                // Empresa
                $formContent = '
                <div class="text-center mb-6">
                    <h1 class="display text-3xl font-extrabold text-slate-900">Registrar Empresa</h1>
                    <p class="mt-2 text-sm text-slate-500">Publica ofertas laborales y conecta con el mejor talento joven disponible.</p>
                </div>
                ' . $tabs . '
                <form method="post" action="?action=register_company" class="space-y-4">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nombre del Reclutador</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconUser('w-5 h-5') . '</span>
                                <input required name="name" value="' . self::e($old['name'] ?? '') . '" placeholder="Ej: Laura Cardona" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Cédula del Reclutador</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconShield('w-5 h-5') . '</span>
                                <input required name="cedula" value="' . self::e($old['cedula'] ?? '') . '" inputmode="numeric" pattern="[0-9.\s]{6,20}" placeholder="Ej: 1.020.203.040" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Fecha de nacimiento (opcional)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconCalendar('w-5 h-5') . '</span>
                            <input name="birth_date" type="date" max="' . self::e($maxBirthDate) . '" value="' . self::e($old['birth_date'] ?? '') . '" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Correo Corporativo</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconMail('w-5 h-5') . '</span>
                            <input required name="email" type="email" value="' . self::e($old['email'] ?? '') . '" placeholder="laura@empresa.com" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contraseña</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconLock('w-5 h-5') . '</span>
                            <input required name="password" type="password" placeholder="Mínimo 6 caracteres" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nombre de la Empresa</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconBuilding('w-5 h-5') . '</span>
                                <input required name="company_name" value="' . self::e($old['company_name'] ?? '') . '" placeholder="Ej: Innova Digital S.A.S." class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Ciudad Sede</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconLocation('w-5 h-5') . '</span>
                                <input required name="city" value="' . self::e($old['city'] ?? '') . '" placeholder="Ej: Bogotá o Medellín" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl bg-blue-50 border border-blue-200 p-3 text-xs text-blue-900 flex items-start gap-2">
                        ' . self::iconShield('w-4 h-4 text-blue-700 flex-shrink-0 mt-0.5') . '
                        <span>La cédula del reclutador se valida contra el registro civil y no puede repetirse en otra cuenta de empresa.</span>
                    </div>
                    ' . $sandboxHint . '
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600 flex items-start gap-2">
                        ' . self::iconCheck('w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5') . '
                        <span>Publicación inmediata de vacantes y panel completo de candidatos.</span>
                    </div>
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] p-3.5 font-bold text-white shadow-md shadow-blue-900/10 transition hover:bg-[#12365f] text-sm">
                        <span>Registrar Empresa y Comenzar</span> ' . self::iconArrowRight('w-4 h-4') . '
                    </button>
                </form>';
            }

            return $alerts . '
            <div class="min-h-[calc(100vh-220px)] flex items-center justify-center py-10 px-4">
                <div class="w-full max-w-lg">
                    <section class="rounded-3xl border border-slate-200/90 bg-white p-8 sm:p-10 shadow-xl shadow-slate-200/50">
                        ' . $formContent . '
                        <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                            <p class="text-sm text-slate-500">
                                ¿Ya tienes una cuenta registrada?
                                <a href="?action=login" class="font-bold text-[#17457c] hover:underline">Ingresar</a>
                            </p>
                        </div>
                    </section>
                </div>
            </div>';
        }

        // LANDING PAGE VIEW (Default)
        $cards = '';
        foreach ($jobs as $job) {
            $modePill = match(strtolower($job['work_mode'])) {
                'remote', 'remoto' => '<span class="rounded-full bg-purple-50 text-purple-700 px-3 py-1 text-xs font-bold border border-purple-200">Remoto</span>',
                'hybrid', 'híbrido' => '<span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold border border-blue-200">Híbrido</span>',
                default => '<span class="rounded-full bg-teal-50 text-teal-700 px-3 py-1 text-xs font-bold border border-teal-200">Presencial</span>'
            };

            $salaryDisplay = ($job['salary_min'] || $job['salary_max'])
                ? '$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.')
                : 'Salario a convenir';

            $cards .= '
            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-extrabold text-base border border-blue-100">
                            ' . strtoupper(substr($job['company_name'], 0, 1)) . '
                        </div>
                        ' . $modePill . '
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 hover:text-[#17457c] transition">
                        <a href="?action=job_detail&id=' . $job['id'] . '">' . self::e($job['title']) . '</a>
                    </h3>
                    <p class="mt-1 text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                        <span class="text-slate-800">' . self::e($job['company_name']) . '</span>
                        <span>·</span>
                        <span>' . self::iconLocation('w-3.5 h-3.5 inline text-slate-400') . ' ' . self::e($job['city']) . '</span>
                    </p>
                    <p class="mt-3.5 text-xs leading-5 text-slate-600 line-clamp-3">' . self::e($job['description']) . '</p>
                </div>
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Rango mensual</span>
                            <span class="text-xs font-extrabold text-slate-900">' . $salaryDisplay . '</span>
                        </div>
                        ' . ($user && $user['role'] === 'candidate' 
                            ? '<form method="post" action="?action=apply" class="inline">
                                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                                <input type="hidden" name="job_id" value="' . $job['id'] . '">
                                <button class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] hover:underline bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-lg transition hover:bg-blue-100">Postularme ' . self::iconArrowRight('w-3 h-3') . '</button>
                            </form>' 
                            : '<a href="?action=job_detail&id=' . $job['id'] . '" class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] hover:underline bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg transition hover:bg-slate-100">Ver detalle ' . self::iconArrowRight('w-3 h-3') . '</a>') . '
                    </div>
                </div>
            </article>';
        }

        if (!$cards) {
            $cards = '<div class="col-span-3 py-12 text-center text-slate-400 text-sm">No se encontraron vacantes con los términos de búsqueda especificados.</div>';
        }

        return $alerts . '
        <!-- HERO SECTION -->
        <section class="landing-hero relative -mx-5 overflow-hidden px-5 py-16 text-white md:-mx-8 md:px-8 md:py-24 rounded-3xl mt-4 mb-12 shadow-xl">
            <div class="absolute inset-0 bg-gradient-to-r from-[#09172a]/95 via-[#0f2b48]/90 to-[#0d2138]/95"></div>
            <div class="relative mx-auto max-w-5xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 border border-blue-400/20 px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-blue-200 mb-6 backdrop-blur">
                    ' . self::iconSpark('w-3.5 h-3.5 text-amber-400') . ' Plataforma de Empleo Juvenil · Youth Employment Bridge
                </div>
                <h1 class="display text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight leading-[1.15] text-white max-w-4xl mx-auto">
                    El próximo gran paso de tu carrera profesional <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-200">empieza aquí.</span>
                </h1>
                <p class="mt-6 max-w-2xl mx-auto text-base sm:text-lg leading-relaxed text-slate-300">
                    Conectamos a estudiantes, recién egresados y talentos emergentes con empresas que apuestan por la motivación, el aprendizaje y el potencial real.
                </p>

                <!-- SEARCH FORM -->
                <form class="relative z-10 mt-10 grid gap-3 rounded-2xl bg-white p-3.5 shadow-2xl text-left md:grid-cols-[1.2fr_1fr_auto] max-w-4xl mx-auto" method="get">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-1">
                        <span class="text-slate-400">' . self::iconSearch('w-5 h-5') . '</span>
                        <input name="q" value="' . self::e($_GET['q'] ?? '') . '" placeholder="Cargo, especialidad o empresa" class="w-full py-2.5 text-slate-900 outline-none text-sm placeholder-slate-400">
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-1">
                        <span class="text-slate-400">' . self::iconLocation('w-5 h-5') . '</span>
                        <input name="location" value="' . self::e($_GET['location'] ?? '') . '" placeholder="Ciudad o modalidad (Remoto / Híbrido)" class="w-full py-2.5 text-slate-900 outline-none text-sm placeholder-slate-400">
                    </div>
                    <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] px-7 py-3 font-bold text-white transition hover:bg-[#12365f] text-sm shadow-md">
                        ' . self::iconSearch('w-4 h-4') . ' Buscar Empleos
                    </button>
                </form>

                <!-- QUICK TAGS -->
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs text-slate-300">
                    <span class="text-slate-400 font-semibold">Búsquedas populares:</span>
                    <a href="?location=Remote" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Remoto</a>
                    <a href="?location=Hybrid" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Híbrido</a>
                    <a href="?q=Junior" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Junior</a>
                    <a href="?q=Frontend" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Frontend</a>
                    <a href="?q=Datos" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Datos</a>
                </div>

                <!-- 4 HERO HIGHLIGHTS -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-12 pt-8 border-t border-white/10 text-left">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/10 text-amber-400">' . self::iconCheck('w-5 h-5') . '</div>
                        <div>
                            <p class="text-sm font-bold text-white">100% Verificado</p>
                            <p class="text-xs text-slate-400">Empresas autenticadas</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/10 text-blue-400">' . self::iconBriefcase('w-5 h-5') . '</div>
                        <div>
                            <p class="text-sm font-bold text-white">Sin Barreras</p>
                            <p class="text-xs text-slate-400">Vacantes de entrada y junior</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/10 text-emerald-400">' . self::iconClock('w-5 h-5') . '</div>
                        <div>
                            <p class="text-sm font-bold text-white">Proceso Ágil</p>
                            <p class="text-xs text-slate-400">Trazabilidad en tiempo real</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/10 text-purple-400">' . self::iconShield('w-5 h-5') . '</div>
                        <div>
                            <p class="text-sm font-bold text-white">Acceso Gratuito</p>
                            <p class="text-xs text-slate-400">Sin costos para candidatos</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- VALUE PROPOSITION (3 PILLARS) -->
        <section class="py-12">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <span class="text-xs font-bold uppercase tracking-widest text-[#17457c]">Propuesta de Valor</span>
                <h2 class="display text-3xl font-extrabold text-slate-900 mt-1">Diseñado para cerrar la brecha laboral juvenil</h2>
                <p class="text-sm text-slate-500 mt-2">Construimos una experiencia simple, transparente y enfocada en resultados para ambas partes.</p>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                <article class="rounded-3xl border border-slate-200/90 bg-white p-7 shadow-sm transition hover:shadow-md">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-[#17457c] mb-5">
                        ' . self::iconUser('w-6 h-6') . '
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Para Candidatos Jóvenes</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Crea tu perfil profesional, adjunta tu hoja de vida y postúlate a oportunidades donde se valora tu talento por encima de años de experiencia.</p>
                </article>
                <article class="rounded-3xl border border-slate-200/90 bg-white p-7 shadow-sm transition hover:shadow-md">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 mb-5">
                        ' . self::iconBuilding('w-6 h-6') . '
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Para Empresas Contratantes</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Publica vacantes en minutos, filtra por modalidad y evalúa candidatos calificados con acceso directo a sus hojas de vida.</p>
                </article>
                <article class="rounded-3xl border border-slate-200/90 bg-white p-7 shadow-sm transition hover:shadow-md">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 mb-5">
                        ' . self::iconShield('w-6 h-6') . '
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Seguimiento Transparente</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Sin incertidumbre ni postulaciones perdidas. Conoce el estado exacto de tu proceso (pendiente, en revisión, entrevista o selección).</p>
                </article>
            </div>
        </section>

        <!-- JOB FEED SECTION -->
        <section class="py-12 border-t border-slate-200/80" id="vacantes">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <span class="text-xs font-bold uppercase tracking-widest text-[#17457c]">Explora Oportunidades</span>
                    <h2 class="display text-3xl font-extrabold text-slate-900 mt-1">Vacantes Destacadas</h2>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-3.5 py-1 text-xs font-bold text-slate-700">
                    ' . count($jobs) . ' vacantes activas
                </span>
            </div>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">' . $cards . '</div>
        </section>

        <!-- HOW IT WORKS (3 STEPS) -->
        <section class="py-16 border-t border-slate-200/80">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-xs font-bold uppercase tracking-widest text-[#17457c]">Paso a Paso</span>
                <h2 class="display text-3xl font-extrabold text-slate-900 mt-1">¿Cómo funciona la plataforma?</h2>
                <p class="text-sm text-slate-500 mt-2">Un proceso directo y sin fricción pensado para perfiles en inicio de carrera.</p>
            </div>
            <div class="grid gap-8 md:grid-cols-3">
                <div class="rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm relative">
                    <span class="text-4xl font-extrabold text-slate-200 block mb-3">01</span>
                    <h3 class="text-lg font-bold text-slate-900">Crea tu perfil digital</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Registra tus datos personales, formación académica, habilidades técnicas y adjunta tu hoja de vida en PDF o Word.</p>
                </div>
                <div class="rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm relative">
                    <span class="text-4xl font-extrabold text-slate-200 block mb-3">02</span>
                    <h3 class="text-lg font-bold text-slate-900">Postúlate en un clic</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Filtra vacantes por modalidad (remoto, híbrido, presencial) y postula directamente a aquellas que se ajusten a tus intereses.</p>
                </div>
                <div class="rounded-3xl border border-slate-200/80 bg-white p-7 shadow-sm relative">
                    <span class="text-4xl font-extrabold text-slate-200 block mb-3">03</span>
                    <h3 class="text-lg font-bold text-slate-900">Conéctate y avanza</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Recibe notificaciones sobre cambios de estado en tus postulaciones y da el salto hacia tu primera gran oportunidad profesional.</p>
                </div>
            </div>
        </section>

        <!-- DUAL CTA BANNER -->
        <section class="py-12 border-t border-slate-200/80">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-3xl bg-gradient-to-br from-[#102a43] to-[#1e40af] p-8 text-white shadow-md">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 mb-5">' . self::iconBuilding('w-6 h-6 text-white') . '</div>
                    <h3 class="text-2xl font-bold">¿Buscas contratar talento joven?</h3>
                    <p class="mt-2 text-sm text-white/80 leading-relaxed">Publica tus ofertas de trabajo, gestiona candidatos y conecta con profesionales motivados con ganas de aprender y crecer en tu empresa.</p>
                    <a href="?action=register_company" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-bold text-[#102a43] transition hover:bg-slate-100 shadow-sm">
                        Registrar mi empresa ' . self::iconArrowRight('w-4 h-4') . '
                    </a>
                </div>
                <div class="rounded-3xl border-2 border-slate-200 bg-white p-8 shadow-md">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-[#17457c] mb-5">' . self::iconUser('w-6 h-6') . '</div>
                    <h3 class="text-2xl font-bold text-slate-900">¿Listo para tu próximo reto?</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">Crea tu perfil en minutos, descubre ofertas afines a tu vocación y postúlate sin costo a las vacantes de empresas verificadas.</p>
                    <a href="?action=register" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#12365f] shadow-sm">
                        Crear perfil de candidato ' . self::iconArrowRight('w-4 h-4') . '
                    </a>
                </div>
            </div>
        </section>';
    }

    public static function chatView(array $application, array $messages, bool $canSend, array $user, ?string $message, ?string $error): string
    {
        $isCandidate = ($user['role'] === 'candidate');
        $backUrl = $isCandidate ? '?action=applications' : '?action=company_applications';
        $backLabel = $isCandidate ? 'Volver a Mis Aplicaciones' : 'Volver a Candidatos';

        $counterpartName = $isCandidate ? $application['company_name'] : $application['candidate_name'];
        $counterpartSub = $isCandidate ? ($application['company_city'] ?? '') : ($application['candidate_email'] ?? '');
        $counterpartInitial = strtoupper(substr($counterpartName, 0, 1));

        $statusConfig = match($application['status']) {
            'accepted' => ['label' => 'Aceptado', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'interview' => ['label' => 'Entrevista', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
            'review' => ['label' => 'En revisión', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
            'rejected' => ['label' => 'Rechazado', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
            default => ['label' => 'Pendiente', 'class' => 'bg-slate-50 text-slate-600 border-slate-200']
        };

        $messagesHtml = '';
        if (empty($messages)) {
            $messagesHtml = '
            <div class="py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-[#17457c] mb-3">
                    ' . self::iconChat('w-7 h-7') . '
                </div>
                <h3 class="text-base font-bold text-slate-800">Inicia la conversación</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    Este canal es directo y privado entre el candidato y la empresa sobre la vacante <strong>"' . self::e($application['job_title']) . '"</strong>.
                </p>
            </div>';
        } else {
            foreach ($messages as $msg) {
                $isMe = ((int) $msg['sender_id'] === (int) $user['id']);
                $dateFormatted = date('d/m/Y H:i', strtotime($msg['created_at']));
                $avatarLetter = strtoupper(substr($msg['sender_name'], 0, 1));

                if ($isMe) {
                    $messagesHtml .= '
                    <div class="flex justify-end gap-2.5 mb-4">
                        <div class="max-w-[85%] sm:max-w-[70%]">
                            <div class="rounded-2xl rounded-tr-sm bg-[#17457c] px-4 py-3 text-white shadow-sm">
                                <p class="text-sm leading-relaxed whitespace-pre-wrap break-words">' . self::e($msg['message']) . '</p>
                            </div>
                            <div class="flex items-center justify-end gap-1.5 mt-1 text-[11px] text-slate-400">
                                <span>' . $dateFormatted . '</span>
                                ' . ($msg['read_at'] ? '<span class="text-[#17457c] font-bold">· Leído</span>' : '<span>· Enviado</span>') . '
                            </div>
                        </div>
                    </div>';
                } else {
                    $senderRoleLabel = ($msg['sender_role'] === 'recruiter') ? 'Empresa' : 'Candidato';
                    $messagesHtml .= '
                    <div class="flex justify-start gap-2.5 mb-4">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-700 font-bold text-xs mt-1">
                            ' . $avatarLetter . '
                        </div>
                        <div class="max-w-[85%] sm:max-w-[70%]">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-bold text-slate-800">' . self::e($msg['sender_name']) . '</span>
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">(' . $senderRoleLabel . ')</span>
                            </div>
                            <div class="rounded-2xl rounded-tl-sm bg-white border border-slate-200/90 px-4 py-3 text-slate-800 shadow-sm">
                                <p class="text-sm leading-relaxed whitespace-pre-wrap break-words">' . self::e($msg['message']) . '</p>
                            </div>
                            <span class="block mt-1 text-[11px] text-slate-400">' . $dateFormatted . '</span>
                        </div>
                    </div>';
                }
            }
        }

        // Bloqueo estricto cuando la postulación es rechazada
        if (!$canSend || $application['status'] === 'rejected') {
            $inputArea = '
            <div class="rounded-2xl border-2 border-rose-200 bg-rose-50/90 p-5 text-center shadow-sm">
                <div class="inline-flex items-center justify-center gap-2 text-rose-800 font-bold text-sm">
                    <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <span>Postulación Rechazada — Mensajería Deshabilitada</span>
                </div>
                <p class="text-xs text-rose-700 mt-1.5 max-w-lg mx-auto">
                    El estado actual de esta postulación es <strong>Rechazado</strong>. El canal de comunicación se encuentra cerrado y ya no es posible enviar nuevos mensajes.
                </p>
            </div>';
        } else {
            $inputArea = '
            <form method="post" action="?action=send_message" class="relative" id="chat-form">
                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                <input type="hidden" name="application_id" value="' . (int) $application['id'] . '">
                <div class="flex items-end gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm focus-within:border-[#17457c] focus-within:ring-2 focus-within:ring-blue-100 transition">
                    <textarea required name="message" id="chat-input" rows="2" placeholder="Escribe un mensaje aquí... (Presiona Enter para enviar, Shift+Enter para nueva línea)" class="flex-1 resize-none bg-transparent text-sm text-slate-800 placeholder-slate-400 focus:outline-none"></textarea>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-[#17457c] px-5 py-2.5 text-xs font-bold text-white transition hover:bg-[#12365f] shadow-sm flex-shrink-0">
                        ' . self::iconChat('w-4 h-4') . ' Enviar
                    </button>
                </div>
            </form>
            <script>
                (function() {
                    var input = document.getElementById("chat-input");
                    var form = document.getElementById("chat-form");
                    if (input && form) {
                        input.addEventListener("keydown", function(e) {
                            if (e.key === "Enter" && !e.shiftKey) {
                                e.preventDefault();
                                if (input.value.trim() !== "") {
                                    form.submit();
                                }
                            }
                        });
                    }
                })();
            </script>';
        }

        return self::alerts($message, $error) . '
        <section class="candidate-page py-6">
            <div class="max-w-4xl mx-auto">
                <div class="mb-4 flex items-center justify-between">
                    <a href="' . $backUrl . '" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline bg-white border border-slate-200 px-3 py-1.5 rounded-xl shadow-sm">
                        ' . self::iconArrowLeft('w-3.5 h-3.5') . ' ' . $backLabel . '
                    </a>
                </div>

                <div class="rounded-3xl border border-slate-200/90 bg-white shadow-sm overflow-hidden flex flex-col" style="min-height: 600px;">
                    <!-- Cabecera del chat -->
                    <div class="p-5 md:p-6 border-b border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white font-extrabold text-lg shadow-sm">
                                ' . $counterpartInitial . '
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold text-slate-900">' . self::e($counterpartName) . '</h2>
                                    <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-bold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                                    <span>' . self::e($counterpartSub) . '</span>
                                    <span>·</span>
                                    <span class="font-semibold text-slate-700">Vacante: ' . self::e($application['job_title']) . '</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de mensajes con scroll -->
                    <div id="chat-messages-container" class="flex-1 p-5 md:p-6 overflow-y-auto bg-slate-50/30" style="max-height: 480px; min-height: 320px;">
                        ' . $messagesHtml . '
                    </div>

                    <!-- Área de entrada / Estado bloqueado -->
                    <div class="p-4 md:p-5 border-t border-slate-100 bg-white">
                        ' . $inputArea . '
                    </div>
                </div>
            </div>
            <script>
                (function() {
                    var container = document.getElementById("chat-messages-container");
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                })();
            </script>
        </section>';
    }

    public static function jobDetail(?array $job, ?array $user): string
    {
        if (!$job) {
            return '
            <div class="py-16 text-center">
                <h1 class="text-2xl font-bold text-slate-700">Vacante no encontrada</h1>
                <p class="mt-2 text-slate-500">Esta vacante ya no se encuentra disponible en la plataforma.</p>
                <a href="./" class="mt-4 inline-flex items-center gap-1 font-bold text-[#17457c]">Volver al inicio ' . self::iconArrowRight('w-4 h-4') . '</a>
            </div>';
        }

        $salary = ($job['salary_min'] || $job['salary_max'])
            ? '$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.')
            : 'Salario a convenir';

        $applyBtn = ($user && $user['role'] === 'candidate')
            ? '<form method="post" action="?action=apply">
                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                <input type="hidden" name="job_id" value="' . $job['id'] . '">
                <button class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-8 py-3.5 font-bold text-white shadow-sm transition hover:bg-[#12365f]">
                    Aplicar a esta vacante ' . self::iconArrowRight('w-4 h-4') . '
                </button>
            </form>'
            : '<a href="?action=login" class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-8 py-3.5 font-bold text-white shadow-sm transition hover:bg-[#12365f]">
                Ingresar para aplicar ' . self::iconArrowRight('w-4 h-4') . '
            </a>';

        $modePill = match(strtolower($job['work_mode'])) {
            'remote', 'remoto' => '<span class="rounded-full bg-purple-50 text-purple-700 px-3 py-1 text-xs font-bold border border-purple-200">Remoto</span>',
            'hybrid', 'híbrido' => '<span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold border border-blue-200">Híbrido</span>',
            default => '<span class="rounded-full bg-teal-50 text-teal-700 px-3 py-1 text-xs font-bold border border-teal-200">Presencial</span>'
        };

        return '
        <section class="mx-auto max-w-4xl py-10">
            <a href="./" class="inline-flex items-center gap-2 text-sm font-bold text-[#17457c] hover:underline mb-6">
                ' . self::iconArrowLeft('w-4 h-4') . ' Volver a ofertas laborales
            </a>
            <div class="rounded-3xl border border-slate-200/90 bg-white p-8 md:p-10 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pb-6 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-[#17457c]">Oportunidad Laboral</span>
                            ' . $modePill . '
                        </div>
                        <h1 class="text-3xl font-extrabold text-slate-900">' . self::e($job['title']) . '</h1>
                        <p class="mt-1.5 text-lg font-semibold text-slate-700">' . self::e($job['company_name'] ?? 'Empresa Reclutadora') . '</p>
                        <p class="mt-1 text-sm text-slate-500">' . self::iconLocation('w-4 h-4 inline text-slate-400') . ' ' . self::e($job['city']) . '</p>
                    </div>
                    <div class="self-start sm:self-auto">' . $applyBtn . '</div>
                </div>

                <div class="my-8 grid gap-4 text-sm text-slate-700 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                        <div class="flex items-center gap-2 text-slate-500">' . self::iconBriefcase('w-4 h-4') . '<span class="text-xs font-medium">Modalidad</span></div>
                        <p class="mt-1 font-bold text-slate-900 text-base">' . self::e($job['work_mode']) . '</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                        <div class="flex items-center gap-2 text-slate-500">' . self::iconClock('w-4 h-4') . '<span class="text-xs font-medium">Experiencia</span></div>
                        <p class="mt-1 font-bold text-slate-900 text-base">' . self::e($job['experience']) . '</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                        <div class="flex items-center gap-2 text-slate-500">' . self::iconCurrency('w-4 h-4') . '<span class="text-xs font-medium">Rango Salarial</span></div>
                        <p class="mt-1 font-bold text-slate-900 text-base">' . $salary . '</p>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-8">
                    <h2 class="text-xl font-bold text-slate-900">Descripción de la vacante</h2>
                    <p class="mt-4 whitespace-pre-line leading-7 text-slate-600 text-sm">' . self::e($job['description']) . '</p>
                </div>
            </div>
        </section>';
    }

    public static function layout(string $content, ?array $user, int $notificationCount = 0, int $chatUnreadCount = 0): string
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
                    ['action' => 'messages', 'label' => 'Mensajes', 'href' => '?action=messages'],
                    ['action' => 'profile', 'label' => 'Hoja de Vida', 'href' => '?action=profile'],
                    ['action' => 'favorites', 'label' => 'Favoritos', 'href' => '?action=favorites'],
                ];
            } elseif ($user['role'] === 'recruiter') {
                $navLinks = [
                    ['action' => 'dashboard', 'label' => 'Resumen', 'href' => '?action=dashboard'],
                    ['action' => 'company_jobs', 'label' => 'Mis vacantes', 'href' => '?action=company_jobs'],
                    ['action' => 'company_applications', 'label' => 'Candidatos', 'href' => '?action=company_applications'],
                    ['action' => 'company_messages', 'label' => 'Mensajes', 'href' => '?action=company_messages'],
                    ['action' => 'company_profile', 'label' => 'Perfil empresa', 'href' => '?action=company_profile'],
                    ['action' => 'home', 'label' => 'Ver portal', 'href' => './'],
                ];
            } elseif ($user['role'] === 'admin') {
                $navLinks = [
                    ['action' => 'dashboard', 'tab' => 'audit', 'label' => 'Auditoría', 'href' => '?action=dashboard&tab=audit'],
                    ['action' => 'dashboard', 'tab' => 'users', 'label' => 'Usuarios', 'href' => '?action=dashboard&tab=users'],
                    ['action' => 'dashboard', 'tab' => 'companies', 'label' => 'Empresas', 'href' => '?action=dashboard&tab=companies'],
                    ['action' => 'dashboard', 'tab' => 'create_admin', 'label' => 'Crear Admin', 'href' => '?action=dashboard&tab=create_admin'],
                    ['action' => 'home', 'tab' => '', 'label' => 'Ver portal', 'href' => './'],
                ];
            }

            if (!empty($navLinks)) {
                $desktopLinksHtml = '<nav class="hidden md:flex items-center gap-1.5 lg:gap-2">';
                $mobileLinksHtml = '<div class="flex md:hidden items-center gap-2 overflow-x-auto py-2 border-t border-slate-100 text-xs font-semibold text-slate-600">';
                foreach ($navLinks as $item) {
                    if ($user['role'] === 'admin') {
                        $currentTab = $_GET['tab'] ?? 'audit';
                        $isActive = ($item['action'] === 'home' && empty($_GET['action'])) ||
                                    ($currentAction === 'dashboard' && ($item['action'] === 'dashboard') && ($currentTab === ($item['tab'] ?? '')));
                    } else {
                        $isActive = ($currentAction === $item['action']) || ($item['action'] === 'home' && empty($_GET['action']));
                    }
                    $deskClass = $isActive 
                        ? 'rounded-xl bg-[#17457c] text-white font-bold px-3.5 py-2 shadow-sm' 
                        : 'rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-semibold px-3.5 py-2 transition';
                    $mobClass = $isActive 
                        ? 'whitespace-nowrap rounded-lg bg-[#17457c] text-white px-2.5 py-1' 
                        : 'whitespace-nowrap rounded-lg bg-slate-100 text-slate-700 px-2.5 py-1';
                    
                    $desktopLinksHtml .= '<a href="' . $item['href'] . '" class="' . $deskClass . ' text-xs lg:text-sm">' . self::e($item['label']) . '</a>';
                    $mobileLinksHtml .= '<a href="' . $item['href'] . '" class="' . $mobClass . '">' . self::e($item['label']) . '</a>';
                }
                $desktopLinksHtml .= '</nav>';
                $mobileLinksHtml .= '</div>';
            }

            $roleLabel = match($user['role']) {
                'recruiter' => 'Empresa',
                'admin' => 'Administrador',
                default => 'Candidato'
            };

            $chatBadge = ($chatUnreadCount > 0)
                ? '<span class="absolute -top-1 -right-1 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white leading-none shadow-sm">' . $chatUnreadCount . '</span>'
                : '';
            $messagesUrl = ($user['role'] === 'candidate') ? '?action=messages' : '?action=company_messages';

            $rightNav = '
            <div class="flex items-center gap-2.5">
                <a href="' . $messagesUrl . '" class="relative flex items-center justify-center h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition" title="Mensajes">
                    ' . self::iconChat('w-5 h-5 text-slate-600') . $chatBadge . '
                </a>
                <a href="?action=alerts" class="relative flex items-center justify-center h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition" title="Notificaciones">
                    ' . self::iconBell('w-5 h-5 text-slate-600') . 
                    ($notificationCount > 0 ? '<span class="absolute -top-1 -right-1 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white leading-none shadow-sm">' . $notificationCount . '</span>' : '') . '
                </a>
                <div class="hidden sm:flex flex-col text-right leading-tight">
                    <span class="font-bold text-sm text-slate-900 max-w-[140px] truncate">' . self::e($user['name']) . '</span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#17457c]">' . $roleLabel . '</span>
                </div>
                <form method="post" action="?action=logout" class="inline">
                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">
                    <button class="rounded-xl border border-slate-200 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 px-3.5 py-2 text-xs font-semibold text-slate-600 transition shadow-sm">Salir</button>
                </form>
            </div>';
        } else {
            $rightNav = '
            <div class="flex items-center gap-3">
                <a href="./#vacantes" class="hidden text-sm font-semibold text-slate-600 hover:text-[#17457c] md:inline px-3 py-2">Explorar ofertas</a>
                <a href="?action=login" class="text-sm font-bold text-slate-700 hover:text-[#17457c] px-3 py-2">Ingresar</a>
                <a href="?action=register" class="inline-flex items-center gap-1.5 rounded-xl bg-[#17457c] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#12365f] shadow-sm">
                    Crear perfil
                </a>
            </div>';
        }

        $headerHtml = '
        <header class="sticky top-0 z-30 border-b border-slate-200/90 bg-white/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-3 md:py-3.5 gap-4">
                <div class="flex items-center gap-6">
                    <a href="./" class="flex items-center gap-2 group">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white shadow-md shadow-blue-900/10">
                            ' . self::iconBriefcase('w-5 h-5 text-white') . '
                        </span>
                        <div>
                            <span class="display text-xl font-extrabold tracking-tight text-[#17457c] block leading-none">YEB<span class="text-amber-500">.</span></span>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block mt-0.5">Youth Employment Bridge</span>
                        </div>
                    </a>
                    ' . $desktopLinksHtml . '
                </div>
                ' . $rightNav . '
            </div>
            ' . ($mobileLinksHtml ? '<div class="px-5 pb-2 max-w-6xl mx-auto">' . $mobileLinksHtml . '</div>' : '') . '
        </header>';

        return '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>YEB | Youth Employment Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Plus Jakarta Sans", sans-serif; }
        .display { font-family: "Fraunces", serif; }
        .landing-hero {
            background-image: radial-gradient(circle at 15% 25%, rgba(37, 99, 235, 0.25) 0%, transparent 45%), radial-gradient(circle at 85% 75%, rgba(245, 158, 11, 0.15) 0%, transparent 45%), linear-gradient(140deg, #09172a 0%, #0f2b48 55%, #081d33 100%);
            background-position: center;
            background-size: cover;
        }
        .filter-pill {
            white-space: nowrap;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #fff;
            padding: 0.45rem 0.95rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            transition: all 0.15s;
        }
        .filter-pill:hover { border-color: #cbd5e1; background: #f8fafc; color: #1e293b; }
        .circle-action {
            display: inline-flex;
            height: 2.75rem;
            width: 2.75rem;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            background: #f1f5f9;
            color: #334155;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }
        .circle-action:hover { background: #e2e8f0; color: #0f172a; }
        .candidate-page {
            min-height: calc(100vh - 90px);
            background: #f8fafc;
            padding: 1.5rem max(1rem, 4vw) 4rem;
            margin: 0 -2rem;
        }
        .profile-tabs {
            display: flex;
            gap: 0.35rem;
            overflow: auto;
            border-radius: 1rem;
            background: #eef2f6;
            padding: 0.35rem;
            margin-bottom: 2rem;
        }
        .profile-tabs a {
            white-space: nowrap;
            border-radius: 0.75rem;
            padding: 0.65rem 1.25rem;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.15s;
        }
        .profile-tabs a:hover { color: #1e293b; }
        .profile-tabs a.active {
            background: #17457c;
            color: #fff;
            box-shadow: 0 2px 8px rgba(23,69,124,0.25);
        }
        .profile-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
            gap: 2rem;
            max-width: 1180px;
            margin: auto;
        }
        .profile-card, .premium-card {
            border-radius: 1.25rem;
            background: #fff;
            padding: 1.75rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.04);
        }
        .profile-card h1 { font-size: 1.35rem; font-weight: 700; color: #1e293b; }
        .profile-card h2 { font-size: 1.2rem; font-weight: 700; color: #1e293b; }
        .profile-card p { color: #64748b; }
        .profile-avatar {
            height: 5.5rem;
            width: 5.5rem;
            border-radius: 1rem;
            object-fit: cover;
            border: 3px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .profile-avatar-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #17457c;
            color: #fff;
            font-size: 1.8rem;
            height: 5.5rem;
            width: 5.5rem;
            border-radius: 1rem;
            font-weight: 800;
        }
        .profile-completion { margin-top: 0.4rem; font-size: 0.85rem; }
        .profile-completion strong { color: #059669; }
        .progress-track {
            height: 0.5rem;
            border-radius: 999px;
            background: #e2e8f0;
            margin-top: 1.2rem;
            overflow: hidden;
        }
        .progress-track span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #10b981;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .stats-grid div {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.85rem;
            color: #475569;
        }
        .stats-grid strong { margin-left: auto; color: #0f172a; font-size: 1rem; }
        .profile-link {
            display: inline-block;
            margin-top: 1.2rem;
            color: #17457c;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .profile-link:hover { text-decoration: underline; }
        .quick-links { display: grid; margin-top: 0.75rem; }
        .quick-links a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #f1f5f9;
            padding: 0.85rem 0;
            color: #334155;
            font-size: 0.925rem;
            font-weight: 500;
            transition: color 0.15s;
        }
        .quick-links a:hover { color: #17457c; }
        .recommendations { padding: 0; overflow: hidden; }
        .recommendations h2 {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }
        .recommendations h2 strong { color: #17457c; }
        .profile-job {
            display: block;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.25rem 1.5rem;
            transition: background 0.15s;
        }
        .profile-job:hover { background: #f8fafc; }
        .profile-job h3 { margin-top: 0.35rem; font-size: 1rem; font-weight: 700; color: #1e293b; }
        .profile-job p { margin-top: 0.2rem; font-size: 0.85rem; }
        .profile-job span {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: #17457c;
        }
        .premium-card {
            background: linear-gradient(135deg, #17457c, #0f2d52);
            color: #fff;
            text-align: center;
            border: 0;
        }
        .premium-card h2 { color: #fff; }
        .premium-card p { margin: 1rem 0; color: #cbd5e1; font-size: 0.925rem; }
        .premium-card a, .primary-button {
            display: inline-block;
            border-radius: 0.75rem;
            background: #17457c;
            padding: 0.75rem 1.5rem;
            color: #fff;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.15s;
            border: 0;
        }
        .premium-card a { background: #fff; color: #17457c; }
        .premium-card a:hover { background: #f8fafc; }
        .primary-button:hover { background: #12365f; }
        .profile-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(250px, 0.5fr);
            gap: 2rem;
            max-width: 1180px;
            margin: auto;
        }
        .page-title {
            max-width: 1180px;
            margin: 0 auto 1.25rem;
            font-size: 1.85rem;
            font-weight: 700;
            color: #1e293b;
        }
        .profile-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.1rem;
            margin-top: 1.5rem;
        }
        .profile-form label {
            display: block;
            color: #334155;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .profile-form label:nth-last-of-type(3),
        .profile-form label:nth-last-of-type(2),
        .profile-form label:nth-last-of-type(1) { grid-column: 1/-1; }
        .profile-form input, .profile-form textarea {
            display: block;
            width: 100%;
            margin-top: 0.35rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            padding: 0.65rem 0.85rem;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .profile-form input:focus, .profile-form textarea:focus {
            border-color: #17457c;
            box-shadow: 0 0 0 3px rgba(23,69,124,0.12);
        }
        .profile-form textarea { min-height: 5.5rem; }
        .application-list { max-width: 900px; margin: auto; }
        .application-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.85rem;
            border-radius: 1rem;
            background: #fff;
            padding: 1.25rem 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.03);
            transition: all 0.15s;
        }
        .application-card:hover { border-color: #cbd5e1; }
        .application-card h2 { font-size: 1rem; font-weight: 700; color: #1e293b; }
        .application-card p { margin-top: 0.25rem; color: #64748b; font-size: 0.85rem; }
        .empty-state { text-align: center; padding: 3.5rem 1.5rem; }
        .empty-state h2 { margin: 1rem 0 0.5rem; font-size: 1.25rem; color: #1e293b; }
        .side-card { align-self: start; }
        @media(max-width: 1023px) {
            .dashboard-shell aside { max-height: none; }
            .dashboard-shell article { min-height: auto; }
            .dashboard-shell { margin-bottom: 2rem; }
            .profile-grid, .profile-layout, .company-columns { grid-template-columns: 1fr; }
            .candidate-page { margin: 0 -1.25rem; }
            .profile-form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 antialiased flex flex-col min-h-screen">
    ' . $headerHtml . '
    <main class="mx-auto max-w-6xl px-5 md:px-8 flex-1 w-full py-4">
        ' . $content . '
    </main>
    <footer class="border-t border-slate-200 bg-white mt-20">
        <div class="mx-auto max-w-6xl px-5 py-12 md:px-8">
            <div class="grid gap-8 md:grid-cols-4 mb-8 pb-8 border-b border-slate-100">
                <div class="md:col-span-1">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white">
                            ' . self::iconBriefcase('w-5 h-5 text-white') . '
                        </span>
                        <span class="display text-xl font-extrabold text-[#17457c]">YEB<span class="text-amber-500">.</span></span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">Youth Employment Bridge es la plataforma que conecta talento emergente con oportunidades laborales de calidad.</p>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-3">Candidatos</h4>
                    <ul class="space-y-2 text-xs text-slate-600">
                        <li><a href="./#vacantes" class="hover:text-[#17457c]">Explorar empleos</a></li>
                        <li><a href="?action=register" class="hover:text-[#17457c]">Crear perfil</a></li>
                        <li><a href="?action=login" class="hover:text-[#17457c]">Mis postulaciones</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-3">Empresas</h4>
                    <ul class="space-y-2 text-xs text-slate-600">
                        <li><a href="?action=register_company" class="hover:text-[#17457c]">Registrar empresa</a></li>
                        <li><a href="?action=login" class="hover:text-[#17457c]">Publicar vacantes</a></li>
                        <li><a href="?action=login" class="hover:text-[#17457c]">Gestión de candidatos</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-3">Plataforma</h4>
                    <ul class="space-y-2 text-xs text-slate-600">
                        <li class="flex items-center gap-1.5">' . self::iconShield('w-3.5 h-3.5 text-emerald-600') . ' Seguridad y Verificación</li>
                        <li>Arquitectura Monolítica Modular</li>
                        <li>PHP 8.2 & MySQL</li>
                    </ul>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-400">
                <span>&copy; ' . date('Y') . ' Youth Employment Bridge (YEB). Todos los derechos reservados.</span>
                <span>Desarrollado para impulsar el empleo y el talento joven.</span>
            </div>
        </div>
    </footer>
</body>
</html>';
    }

    private static function alerts(?string $message, ?string $error): string
    {
        $html = '';
        if ($error) {
            $html .= '
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-800 shadow-sm">
                <span class="mt-0.5 text-rose-600">' . self::iconAlert('w-5 h-5') . '</span>
                <div class="flex-1 font-medium">' . self::e($error) . '</div>
            </div>';
        }
        if ($message) {
            $html .= '
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm text-emerald-800 shadow-sm">
                <span class="mt-0.5 text-emerald-600">' . self::iconCheck('w-5 h-5') . '</span>
                <div class="flex-1 font-medium">' . self::e($message) . '</div>
            </div>';
        }
        return $html;
    }

    private static function field(string $name, string $label, string $type = 'text', string $placeholder = ''): string
    {
        $labelHtml = '<label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">' . $label . '</label>';
        if ($type === 'textarea') {
            return '<div>' . $labelHtml . '<textarea required name="' . $name . '" placeholder="' . self::e($placeholder) . '" class="w-full rounded-xl border border-slate-200 p-3 text-sm text-slate-800 min-h-[120px] focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent"></textarea></div>';
        }
        return '<div>' . $labelHtml . '<input required name="' . $name . '" type="' . $type . '" placeholder="' . self::e($placeholder) . '" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent"></div>';
    }

    private static function fieldValue(string $name, string $label, ?string $value, string $type = 'text'): string
    {
        $value = self::e($value ?? '');
        $labelHtml = '<label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">' . $label . '</label>';
        if ($type === 'textarea') {
            return '<div class="md:col-span-2">' . $labelHtml . '<textarea name="' . $name . '" class="w-full rounded-xl border border-slate-200 p-3 text-sm text-slate-800 min-h-[100px] focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent">' . $value . '</textarea></div>';
        }
        return '<div>' . $labelHtml . '<input name="' . $name . '" type="' . $type . '" value="' . $value . '" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent"></div>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    // ==========================================
    // SVG ICONS LIBRARY (NO EMOJIS)
    // ==========================================

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

    private static function iconCalendar(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
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
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';
    }

    private static function iconUser(string $class = 'w-6 h-6'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';
    }

    private static function iconDocument(string $class = 'w-6 h-6'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
    }

    private static function iconBuilding(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>';
    }

    private static function iconShield(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>';
    }

    private static function iconChart(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>';
    }

    private static function iconPlus(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>';
    }

    private static function iconMail(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
    }

    private static function iconLock(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';
    }

    private static function iconArrowRight(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>';
    }

    private static function iconArrowLeft(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>';
    }

    private static function iconDownload(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>';
    }

    private static function iconSpark(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>';
    }

    private static function iconAlert(string $class = 'w-5 h-5'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
    }

    private static function iconTrash(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
    }

    private static function iconFilter(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>';
    }

    private static function iconUserPlus(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>';
    }

    private static function iconChat(string $class = 'w-4 h-4'): string
    {
        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>';
    }
}
