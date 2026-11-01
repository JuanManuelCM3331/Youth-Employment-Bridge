<?php

namespace Modules\Jobs\UI;

use App\Http\PortalViewRenderer;

final class JobsUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (listados y detalle de vacantes).
     */
    public static function styles(): string
    {
        return '
        /* Tabla de vacantes: filas alternas y aparicion escalonada */
        :where(#jobTable) tbody tr:nth-child(even) { background-color: #fbfcfe; }
        :where(#jobTable) tbody tr:nth-child(-n+10) { animation: yeb-row-in 0.24s ease-out backwards; }
        :where(#jobTable) tbody tr:nth-child(2) { animation-delay: 0.03s; }
        :where(#jobTable) tbody tr:nth-child(3) { animation-delay: 0.06s; }
        :where(#jobTable) tbody tr:nth-child(4) { animation-delay: 0.09s; }
        :where(#jobTable) tbody tr:nth-child(n+5) { animation-delay: 0.12s; }
        ';
    }

    public static function adminJobsView(array $jobs): string
    {

        $rows = '';

        foreach ($jobs as $job) {

            $statusConfig = match ($job['status']) {

                'published' => ['label' => 'Publicada', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],

                'closed' => ['label' => 'Cerrada', 'class' => 'bg-slate-50 text-slate-600 border-slate-200'],

                default => ['label' => 'Borrador', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],

            };



            $actionBtn = '

            <form method="post" action="?action=admin_delete_job" class="inline" onsubmit="return confirm(\'¿Estás seguro de eliminar la vacante \\\' ' . addslashes(self::e($job['title'])) . ' \\\'? Se eliminarán también sus postulaciones y mensajes.\')">

                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                <input type="hidden" name="job_id" value="' . $job['id'] . '">

                <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 hover:border-rose-300 transition shadow-sm">

                    ' . self::iconTrash('w-3.5 h-3.5') . ' Eliminar

                </button>

            </form>';



            $rows .= '

            <tr class="job-row border-t border-slate-100 hover:bg-slate-50/70 transition">

                <td class="px-4 py-3.5 text-xs text-slate-400 font-mono">#' . $job['id'] . '</td>

                <td class="px-4 py-3.5">

                    <p class="font-bold text-sm text-slate-900">' . self::e($job['title']) . '</p>

                    <p class="text-xs text-slate-500 flex items-center gap-1">' . self::iconBuilding('w-3 h-3 text-slate-400') . ' ' . self::e($job['company_name'] ?? 'Sin empresa') . '</p>

                </td>

                <td class="px-4 py-3.5 text-xs text-slate-600">' . self::e($job['city']) . '</td>

                <td class="px-4 py-3.5 text-xs text-slate-600">' . self::e($job['work_mode']) . '</td>

                <td class="px-4 py-3.5 text-xs">

                    <span class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-bold ' . $statusConfig['class'] . '">' . self::e($statusConfig['label']) . '</span>

                </td>

                <td class="px-4 py-3.5 text-xs">

                    <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 border border-blue-200">

                        ' . self::iconUser('w-3.5 h-3.5') . ' ' . (int) ($job['applications_count'] ?? 0) . '

                    </span>

                </td>

                <td class="px-4 py-3.5 text-xs text-slate-500 font-mono">' . date('Y-m-d', strtotime($job['created_at'])) . '</td>

                <td class="px-4 py-3.5 text-right whitespace-nowrap">' . $actionBtn . '</td>

            </tr>';

        }



        if (!$rows) {

            $rows = '<tr><td colspan="8" class="p-12 text-center text-sm text-slate-400">No hay vacantes publicadas todavía.</td></tr>';

        }



        return '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-slate-100">

                <div>

                    <h2 class="text-2xl font-bold text-slate-900">Gestión de Vacantes</h2>

                    <p class="text-xs text-slate-500 mt-1">Elimina vacantes publicadas en el portal junto con sus postulaciones y mensajes.</p>

                </div>

                <div class="flex items-center gap-3">

                    <div class="relative w-full sm:w-72">

                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>

                        <input id="jobSearchInput" onkeyup="filterJobTable()" type="text" placeholder="Buscar por puesto, empresa o ciudad..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">

                    </div>

                    <span class="whitespace-nowrap rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700">' . count($jobs) . ' vacantes</span>

                </div>

            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100">

                <table id="jobTable" class="w-full min-w-[900px] text-left text-sm">

                    <thead class="bg-slate-50/80 border-b border-slate-200/80">

                        <tr class="text-xs font-bold uppercase tracking-wider text-slate-500">

                            <th class="px-4 py-3">ID</th>

                            <th class="px-4 py-3">Vacante</th>

                            <th class="px-4 py-3">Ciudad</th>

                            <th class="px-4 py-3">Modalidad</th>

                            <th class="px-4 py-3">Estado</th>

                            <th class="px-4 py-3">Postulaciones</th>

                            <th class="px-4 py-3">Fecha</th>

                            <th class="px-4 py-3 text-right">Acción</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">' . $rows . '</tbody>

                </table>

            </div>

        </div>

        <script>

        function filterJobTable() {

            var input = document.getElementById("jobSearchInput");

            var filter = input.value.toLowerCase();

            var rows = document.querySelectorAll(".job-row");

            rows.forEach(function(row) {

                var text = row.textContent.toLowerCase();

                row.style.display = text.indexOf(filter) > -1 ? "" : "none";

            });

        }

        </script>';

    }


    public static function companyJobsView(array $jobs, array $user): string
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



            $statusConfig = match ($job['status']) {

                'published' => ['label' => 'Publicada', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],

                'draft' => ['label' => 'Borrador', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],

                default => ['label' => 'Cerrada', 'class' => 'bg-slate-100 text-slate-600 border-slate-200']

            };



            $modePill = match (strtolower($job['work_mode'])) {

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

                        <button class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700">

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


    public static function jobDetail(?array $job, ?array $user, ?string $message = null, ?string $error = null): string
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

                <input type="hidden" name="return_to" value="?action=job_detail&amp;id=' . $job['id'] . '">

                <button class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-3.5 font-bold text-white shadow-md transition hover:bg-emerald-700">

                    Aplicar a esta vacante ' . self::iconArrowRight('w-4 h-4') . '

                </button>

            </form>'

            : '<a href="?action=login" class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-8 py-3.5 font-bold text-white shadow-sm transition hover:bg-[#12365f]">

                Ingresar para aplicar ' . self::iconArrowRight('w-4 h-4') . '

            </a>';



        $modePill = match (strtolower($job['work_mode'])) {

            'remote', 'remoto' => '<span class="rounded-full bg-purple-50 text-purple-700 px-3 py-1 text-xs font-bold border border-purple-200">Remoto</span>',

            'hybrid', 'híbrido' => '<span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold border border-blue-200">Híbrido</span>',

            default => '<span class="rounded-full bg-teal-50 text-teal-700 px-3 py-1 text-xs font-bold border border-teal-200">Presencial</span>'

        };



        return self::alerts($message, $error) . '

        <section class="mx-auto max-w-4xl py-10">

            <a href="./" class="inline-flex items-center gap-2 text-sm font-bold text-[#17457c] hover:underline mb-6">

                ' . self::iconArrowLeft('w-4 h-4') . ' Volver a ofertas laborales

            </a>

            <div class="rounded-3xl border border-slate-300/90 border-t-4 border-t-[#17457c] bg-white p-8 md:p-10 shadow-sm">

                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pb-6 border-b border-slate-100">

                    <div>

                        <div class="flex items-center gap-2 mb-2">

                            <span class="text-xs font-bold uppercase tracking-wider text-[#17457c]">Oportunidad Laboral</span>

                            ' . $modePill . '

                        </div>

                        <h1 class="break-words text-3xl font-extrabold text-slate-900">' . self::e($job['title']) . '</h1>

                        <p class="mt-1.5 break-words text-lg font-semibold text-slate-700">' . self::e($job['company_name'] ?? 'Empresa Reclutadora') . '</p>

                        <p class="mt-1 break-words text-sm text-slate-500">' . self::iconLocation('w-4 h-4 inline text-slate-400') . ' ' . self::e($job['city']) . '</p>

                    </div>

                    <div class="self-start sm:self-auto">' . $applyBtn . '</div>

                </div>



                <div class="my-8 grid gap-4 text-sm text-slate-700 sm:grid-cols-3">

                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4">

                        <div class="flex items-center gap-2 text-indigo-700">' . self::iconBriefcase('w-4 h-4') . '<span class="text-xs font-bold uppercase tracking-wide">Modalidad</span></div>

                        <p class="mt-1 font-bold text-slate-900 text-base">' . self::e($job['work_mode']) . '</p>

                    </div>

                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4">

                        <div class="flex items-center gap-2 text-amber-700">' . self::iconClock('w-4 h-4') . '<span class="text-xs font-bold uppercase tracking-wide">Experiencia</span></div>

                        <p class="mt-1 font-bold text-slate-900 text-base">' . self::e($job['experience']) . '</p>

                    </div>

                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4">

                        <div class="flex items-center gap-2 text-emerald-700">' . self::iconCurrency('w-4 h-4') . '<span class="text-xs font-bold uppercase tracking-wide">Rango Salarial</span></div>

                        <p class="mt-1 font-bold text-slate-900 text-base">' . $salary . '</p>

                    </div>

                </div>



                <div class="border-t border-slate-100 pt-8">

                    <h2 class="text-xl font-bold text-slate-900">Descripción de la vacante</h2>

                    <p class="mt-4 break-words whitespace-pre-line leading-7 text-slate-600 text-sm">' . self::e($job['description']) . '</p>

                </div>

            </div>

        </section>';

    }
}
