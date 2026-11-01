<?php

namespace Modules\Applications\UI;

use App\Http\PortalViewRenderer;
use Modules\Applications\Controllers\ApplicationsController;
use Modules\Dashboard\UI\DashboardUI;

final class ApplicationsUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (postulaciones y candidatos).
     */
    public static function styles(): string
    {
        return '
        /* Tabla de administracion: filas alternas y aparicion escalonada */
        :where(#applicationTable) tbody tr:nth-child(even) { background-color: #fbfcfe; }
        :where(#applicationTable) tbody tr:nth-child(-n+10) { animation: yeb-row-in 0.24s ease-out backwards; }
        :where(#applicationTable) tbody tr:nth-child(2) { animation-delay: 0.03s; }
        :where(#applicationTable) tbody tr:nth-child(3) { animation-delay: 0.06s; }
        :where(#applicationTable) tbody tr:nth-child(4) { animation-delay: 0.09s; }
        :where(#applicationTable) tbody tr:nth-child(n+5) { animation-delay: 0.12s; }
        ';
    }

    public static function adminApplicationsView(array $applications): string
    {

        $rows = '';

        foreach ($applications as $application) {

            $statusConfig = match ($application['status']) {

                'accepted' => ['label' => 'Aceptado', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],

                'interview' => ['label' => 'Entrevista', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],

                'review' => ['label' => 'En revisión', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],

                'rejected' => ['label' => 'Rechazado', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],

                default => ['label' => 'Pendiente', 'class' => 'bg-slate-50 text-slate-600 border-slate-200'],

            };



            $actionBtn = '

            <form method="post" action="?action=admin_delete_application" class="inline" onsubmit="return confirm(\'¿Estás seguro de eliminar la postulación de \\\' ' . addslashes(self::e($application['candidate_name'])) . '\\\'? Se eliminarán también sus mensajes.\')">

                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                <input type="hidden" name="application_id" value="' . $application['id'] . '">

                <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 hover:border-rose-300 transition shadow-sm">

                    ' . self::iconTrash('w-3.5 h-3.5') . ' Eliminar

                </button>

            </form>';



            $rows .= '

            <tr class="application-row border-t border-slate-100 hover:bg-slate-50/70 transition">

                <td class="px-4 py-3.5 text-xs text-slate-400 font-mono">#' . $application['id'] . '</td>

                <td class="px-4 py-3.5">

                    <div class="flex items-center gap-3">

                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 font-extrabold text-xs">' . strtoupper(substr((string) ($application['candidate_name'] ?? '?'), 0, 1)) . '</div>

                        <div>

                            <p class="font-bold text-sm text-slate-900">' . self::e($application['candidate_name'] ?? 'Candidato eliminado') . '</p>

                            <p class="text-xs text-slate-500">' . self::e($application['candidate_email'] ?? '') . '</p>

                        </div>

                    </div>

                </td>

                <td class="px-4 py-3.5 text-xs font-semibold text-slate-700">' . self::e($application['job_title']) . '</td>

                <td class="px-4 py-3.5 text-xs text-slate-600">' . self::e($application['company_name'] ?? '—') . '</td>

                <td class="px-4 py-3.5 text-xs">

                    <span class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-bold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span>

                </td>

                <td class="px-4 py-3.5 text-xs text-slate-500 font-mono">' . date('Y-m-d', strtotime($application['created_at'])) . '</td>

                <td class="px-4 py-3.5 text-right whitespace-nowrap">' . $actionBtn . '</td>

            </tr>';

        }



        if (!$rows) {

            $rows = '<tr><td colspan="7" class="p-12 text-center text-sm text-slate-400">No hay postulaciones registradas todavía.</td></tr>';

        }



        return '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-slate-100">

                <div>

                    <h2 class="text-2xl font-bold text-slate-900">Gestión de Postulaciones</h2>

                    <p class="text-xs text-slate-500 mt-1">Elimina postulaciones enviadas por los candidatos junto con la conversación asociada.</p>

                </div>

                <div class="flex items-center gap-3">

                    <div class="relative w-full sm:w-72">

                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>

                        <input id="applicationSearchInput" onkeyup="filterApplicationTable()" type="text" placeholder="Buscar por candidato, vacante o empresa..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">

                    </div>

                    <span class="whitespace-nowrap rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700">' . count($applications) . ' postulaciones</span>

                </div>

            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100">

                <table id="applicationTable" class="w-full min-w-[900px] text-left text-sm">

                    <thead class="bg-slate-50/80 border-b border-slate-200/80">

                        <tr class="text-xs font-bold uppercase tracking-wider text-slate-500">

                            <th class="px-4 py-3">ID</th>

                            <th class="px-4 py-3">Candidato</th>

                            <th class="px-4 py-3">Vacante</th>

                            <th class="px-4 py-3">Empresa</th>

                            <th class="px-4 py-3">Estado</th>

                            <th class="px-4 py-3">Fecha</th>

                            <th class="px-4 py-3 text-right">Acción</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">' . $rows . '</tbody>

                </table>

            </div>

        </div>

        <script>

        function filterApplicationTable() {

            var input = document.getElementById("applicationSearchInput");

            var filter = input.value.toLowerCase();

            var rows = document.querySelectorAll(".application-row");

            rows.forEach(function(row) {

                var text = row.textContent.toLowerCase();

                row.style.display = text.indexOf(filter) > -1 ? "" : "none";

            });

        }

        </script>';

    }


    public static function companyApplicationsView(array $applications, array $user): string
    {
        $statusMeta = [
            'pending'   => ['label' => 'Pendiente',   'chip' => 'bg-slate-100 text-slate-600 border-slate-200'],
            'review'    => ['label' => 'En revisión', 'chip' => 'bg-amber-50 text-amber-700 border-amber-200'],
            'interview' => ['label' => 'Entrevista',  'chip' => 'bg-blue-50 text-blue-700 border-blue-200'],
            'accepted'  => ['label' => 'Aceptado',    'chip' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'rejected'  => ['label' => 'Rechazado',   'chip' => 'bg-rose-50 text-rose-700 border-rose-200'],
        ];

        // Agrupa las postulaciones por vacante conservando el orden de llegada (más recientes primero).
        $vacancies = [];
        foreach ($applications as $item) {
            $vacancy = trim((string) ($item['title'] ?? ''));
            if ($vacancy === '') $vacancy = 'Vacante sin título';

            if (!isset($vacancies[$vacancy])) {
                $vacancies[$vacancy] = ['title' => $vacancy, 'total' => 0, 'statuses' => [], 'cards' => ''];
            }

            $status = (string) ($item['status'] ?? 'pending');
            if (!isset($statusMeta[$status])) $status = 'pending';
            $vacancies[$vacancy]['total']++;
            $vacancies[$vacancy]['statuses'][$status] = ($vacancies[$vacancy]['statuses'][$status] ?? 0) + 1;

            $hasCv = !empty($item['cv_bytes']) || (!empty($item['cv_path']) && !str_starts_with((string) $item['cv_path'], 'db://') && is_file($item['cv_path']));

            $cv = $hasCv
                ? '<a class="inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline bg-blue-50 border border-blue-200 px-3 py-1 rounded-lg" href="?action=download_cv&candidate=' . $item['candidate_id'] . '">' . self::iconDownload('w-3.5 h-3.5') . ' Descargar Hoja de Vida</a>'
                : '<span class="text-xs text-slate-400 font-medium bg-slate-100 px-2.5 py-1 rounded-lg">Sin CV adjunto</span>';

            $profileLink = '<a class="inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline bg-slate-100 border border-slate-200 px-3 py-1 rounded-lg" href="?action=candidate_public_profile&candidate=' . (int) $item['candidate_id'] . '&application_id=' . (int) $item['id'] . '">' . self::iconUser('w-3.5 h-3.5') . ' Ver perfil</a>';

            $statusOptions = '';
            foreach (['pending' => 'Pendiente', 'review' => 'En revisión', 'interview' => 'Entrevista', 'accepted' => 'Aceptado', 'rejected' => 'Rechazado'] as $val => $label) {
                $statusOptions .= '<option value="' . $val . '"' . ($item['status'] === $val ? ' selected' : '') . '>' . $label . '</option>';
            }

            $currentBadge = '<span class="rounded-full border px-2.5 py-0.5 text-xs font-bold ' . $statusMeta[$status]['chip'] . '">' . $statusMeta[$status]['label'] . '</span>';

            $appliedAt = !empty($item['created_at'])
                ? '<span>Postulación: <span class="font-semibold text-slate-700">' . self::e(date('d/m/Y', strtotime((string) $item['created_at']))) . '</span></span>'
                : '';

            $vacancies[$vacancy]['cards'] .= '
            <article class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-slate-300">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-extrabold text-base border border-blue-100">' . strtoupper(substr($item['candidate_name'], 0, 1)) . '</div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-base font-bold text-slate-900">' . self::e($item['candidate_name']) . '</h4>
                                ' . $currentBadge . '
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1">' . self::iconMail('w-3.5 h-3.5 text-slate-400') . ' ' . self::e($item['email']) . '</span>
                                ' . ($appliedAt ? '<span>·</span>' . $appliedAt : '') . '
                            </p>
                            <div class="mt-2.5 flex flex-wrap items-center gap-2">' . $profileLink . $cv . '</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 self-end md:self-center">
                        ' . match ($item['status']) {
                'interview' => '<a href="?action=chat&application_id=' . $item['id'] . '" class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50/80 hover:bg-blue-100 text-[#17457c] px-3.5 py-2 text-xs font-bold transition shadow-sm">' . self::iconChat('w-3.5 h-3.5') . ' Mensajes</a>',
                'accepted', 'rejected' => '<a href="?action=chat&application_id=' . $item['id'] . '" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 hover:bg-slate-100 px-3.5 py-2 text-xs font-semibold transition shadow-sm" title="Chat cerrado">' . self::iconChat('w-3.5 h-3.5') . ' Chat cerrado</a>',
                default => '<span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 text-slate-400 px-3.5 py-2 text-xs font-medium cursor-not-allowed" title="El chat se habilitará cuando cambies el estado a Entrevista">' . self::iconChat('w-3.5 h-3.5') . ' Chat inactivo</span>',
            } . '
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

        $sections = '';
        foreach ($vacancies as $vacancy) {
            $chips = '';
            foreach ($statusMeta as $key => $meta) {
                $count = $vacancy['statuses'][$key] ?? 0;
                if ($count <= 0) continue;
                $chips .= '<span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-bold ' . $meta['chip'] . '">' . $count . ' ' . $meta['label'] . '</span>';
            }

            $sections .= '
            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
                <header class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#17457c] text-white shadow-sm">' . self::iconBriefcase('w-5 h-5') . '</div>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Vacante</p>
                            <h3 class="text-base font-bold text-slate-900">' . self::e($vacancy['title']) . '</h3>
                            <p class="text-xs text-slate-500">' . $vacancy['total'] . ' ' . ($vacancy['total'] === 1 ? 'candidato postulado' : 'candidatos postulados') . '</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">' . $chips . '</div>
                </header>
                <div class="space-y-3 p-4 md:p-5">' . $vacancy['cards'] . '</div>
            </section>';
        }

        return '
        <div class="max-w-5xl mx-auto">
            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 mb-6">
                    <div>
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200 mb-1">' . self::iconUser('w-3.5 h-3.5') . ' Postulantes</div>
                        <h2 class="text-2xl font-bold text-slate-900">Candidatos por vacante</h2>
                        <p class="text-xs text-slate-500 mt-1">Cada vacante agrupa únicamente a los candidatos que se postularon a ella. Revisa perfiles, descarga hojas de vida y actualiza el estado de cada postulación.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                        <span class="rounded-xl bg-[#17457c] px-4 py-2 text-xs font-bold text-white shadow-sm">' . count($vacancies) . ' ' . (count($vacancies) === 1 ? 'vacante' : 'vacantes') . '</span>
                        <span class="rounded-xl bg-slate-100 border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700">' . count($applications) . ' postulaciones</span>
                    </div>
                </div>
                <div class="space-y-5">' . ($sections ?: '<div class="py-16 text-center text-sm text-slate-400">Aún no se registran postulaciones para tus vacantes.</div>') . '</div>
            </article>
        </div>';
    }


    public static function candidateApplications(array $user, ?string $message, ?string $error, ApplicationsController $applicationsController): string
    {

        $rows = '';

        foreach ($applicationsController->byUser($user['id']) as $item) {

            $statusConfig = match ($item['status']) {

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

                    <a href="?action=company_public_profile&company=' . (int) $item['company_id'] . '" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 px-3.5 py-1.5 text-xs font-bold transition shadow-sm">

                        ' . self::iconBuilding('w-3.5 h-3.5') . ' Ver empresa

                    </a>

                    ' . match ($item['status']) {
                'interview' => '<a href="?action=chat&application_id=' . $item['id'] . '" class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50/80 hover:bg-blue-100 text-[#17457c] px-3.5 py-1.5 text-xs font-bold transition shadow-sm">' . self::iconChat('w-3.5 h-3.5') . ' Mensajes</a>',
                'accepted', 'rejected' => '<a href="?action=chat&application_id=' . $item['id'] . '" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 hover:bg-slate-100 px-3.5 py-1.5 text-xs font-semibold transition shadow-sm" title="Chat cerrado">' . self::iconChat('w-3.5 h-3.5') . ' Chat cerrado</a>',
                default => '<span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 text-slate-400 px-3.5 py-1.5 text-xs font-medium cursor-not-allowed" title="El chat estará disponible cuando tu postulación pase a entrevista">' . self::iconChat('w-3.5 h-3.5') . ' Chat inactivo</span>',
            } . '

                </div>

            </article>';

        }

        return self::alerts($message, $error) . '<section class="candidate-page">' . DashboardUI::candidateNav('applications') . '<h1 class="page-title">Mis aplicaciones</h1><div class="application-list space-y-3">' . ($rows ?: '<div class="profile-card p-8 text-center text-slate-500">Aún no tienes aplicaciones registradas.</div>') . '</div></section>';

    }
}
