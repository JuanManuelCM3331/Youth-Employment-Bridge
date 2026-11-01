<?php

namespace Modules\Identity\UI;

use App\Http\PortalViewRenderer;
use Modules\Identity\Dto\IdScanConfig;

final class IdentityUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (menores y escaneo de identidad).
     */
    public static function styles(): string
    {
        return '
        /* Tabla de menores: filas alternas y aparicion escalonada */
        :where(#minorTable) tbody tr:nth-child(even) { background-color: #fbfcfe; }
        :where(#minorTable) tbody tr:nth-child(-n+10) { animation: yeb-row-in 0.24s ease-out backwards; }
        :where(#minorTable) tbody tr:nth-child(2) { animation-delay: 0.03s; }
        :where(#minorTable) tbody tr:nth-child(3) { animation-delay: 0.06s; }
        :where(#minorTable) tbody tr:nth-child(4) { animation-delay: 0.09s; }
        :where(#minorTable) tbody tr:nth-child(n+5) { animation-delay: 0.12s; }

        /* Recuadro de escaneo: resaltado al usarlo */
        #idScanSection { transition: border-color 0.2s ease, box-shadow 0.2s ease; }
        #idScanSection:focus-within { border-color: #17457c; box-shadow: 0 0 0 3px rgba(23, 69, 124, 0.1); }
        ';
    }

    public static function adminMinorsView(array $minors, array $currentUser): string
    {

        $total = count($minors);

        $blockedCount = count(array_filter($minors, static fn($m) => (int) ($m['minor_blocked'] ?? 0) === 1));

        $unblockedCount = count(array_filter($minors, static fn($m) => (int) ($m['minor_blocked'] ?? 0) === 0));

        $withDocsCount = count(array_filter($minors, static fn($m) => !empty($m['has_documents'])));



        $metricCards = '

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Menores</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">' . self::iconUser('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . $total . '</p>

                <span class="mt-1 block text-xs font-medium text-slate-500">Aspirantes de 16 y 17 años</span>

            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Bloqueados / Pendientes</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600">' . self::iconLock('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . $blockedCount . '</p>

                <span class="mt-1 block text-xs font-medium text-slate-500">Requieren validación o desbloqueo</span>

            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Con Documentos Radicados</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">' . self::iconDocument('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . $withDocsCount . '</p>

                <span class="mt-1 block text-xs font-medium text-slate-500">Los 3 archivos adjuntos</span>

            </div>

            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">

                <div class="flex items-center justify-between">

                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Habilitados</span>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">' . self::iconCheck('w-5 h-5') . '</div>

                </div>

                <p class="mt-3 text-3xl font-extrabold text-slate-900">' . $unblockedCount . '</p>

                <span class="mt-1 block text-xs font-medium text-slate-500">Bloqueo retirado por admin</span>

            </div>

        </div>';



        $rows = '';

        foreach ($minors as $m) {

            $isBlocked = (int) ($m['minor_blocked'] ?? 0) === 1;



            $statusBadge = $isBlocked

                ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 border border-rose-200"><span class="h-1.5 w-1.5 rounded-full bg-rose-600"></span> Bloqueado</span>'

                : '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 border border-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span> Habilitado</span>';



            $docsHtml = '';

            if (!empty($m['civil_registry_path'])) {

                $docsHtml .= '<div class="mb-1"><a href="?action=download_minor_doc&user_id=' . $m['id'] . '&type=civil_registry" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg px-2 py-0.5" title="Descargar Registro Civil">' . self::iconDownload('w-3.5 h-3.5') . ' <span>Registro Civil</span></a></div>';

            } else {

                $docsHtml .= '<div class="text-[11px] text-slate-400 mb-1">❌ Registro Civil (Pendiente)</div>';

            }



            if (!empty($m['parent_id_path'])) {

                $docsHtml .= '<div class="mb-1"><a href="?action=download_minor_doc&user_id=' . $m['id'] . '&type=parent_id" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg px-2 py-0.5" title="Descargar Cédula de los Padres">' . self::iconDownload('w-3.5 h-3.5') . ' <span>Cédula Padres</span></a></div>';

            } else {

                $docsHtml .= '<div class="text-[11px] text-slate-400 mb-1">❌ Cédula Padres (Pendiente)</div>';

            }



            if (!empty($m['authorization_letter_path'])) {

                $docsHtml .= '<div><a href="?action=download_minor_doc&user_id=' . $m['id'] . '&type=authorization_letter" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-purple-700 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg px-2 py-0.5" title="Descargar Carta de Autorización">' . self::iconDownload('w-3.5 h-3.5') . ' <span>Carta Autorización</span></a></div>';

            } else {

                $docsHtml .= '<div class="text-[11px] text-slate-400">❌ Carta Autorización (Pendiente)</div>';

            }



            $actionBtn = '';

            if ($isBlocked) {

                $actionBtn = '

                <form method="post" action="?action=admin_unblock_minor" class="inline" onsubmit="return confirm(\'¿Estás seguro de quitarle el bloqueo al aspirante \\\' ' . addslashes(self::e($m['name'])) . ' \\\'? Podrá interactuar plenamente con la plataforma.\')">

                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                    <input type="hidden" name="user_id" value="' . $m['id'] . '">

                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-600 hover:bg-emerald-700 px-3.5 py-1.5 text-xs font-bold text-white transition shadow-sm">

                        ' . self::iconCheck('w-3.5 h-3.5') . ' Quitar Bloqueo

                    </button>

                </form>';

            } else {

                $actionBtn = '

                <form method="post" action="?action=admin_block_minor" class="inline" onsubmit="return confirm(\'¿Deseas volver a bloquear al aspirante \\\' ' . addslashes(self::e($m['name'])) . ' \\\'?\')">

                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                    <input type="hidden" name="user_id" value="' . $m['id'] . '">

                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 px-3.5 py-1.5 text-xs font-bold text-amber-800 transition shadow-sm">

                        ' . self::iconLock('w-3.5 h-3.5') . ' Bloquear

                    </button>

                </form>';

            }



            $dateFormatted = date('Y-m-d', strtotime($m['created_at']));

            $ageDisplay = (int) $m['age'] . ' años';

            if (!empty($m['birth_date'])) {

                $ageDisplay .= ' <span class="text-[11px] text-slate-400 block font-mono">(' . self::e($m['birth_date']) . ')</span>';

            }



            $rows .= '

            <tr class="minor-row border-t border-slate-100 hover:bg-slate-50/70 transition">

                <td class="px-4 py-3.5 text-xs text-slate-400 font-mono">#' . $m['id'] . '</td>

                <td class="px-4 py-3.5">

                    <div class="flex items-center gap-3">

                        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800 font-extrabold text-xs">' . strtoupper(substr($m['name'], 0, 1)) . '</div>

                        <div>

                            <p class="font-bold text-sm text-slate-900">' . self::e($m['name']) . '</p>

                            <p class="text-xs text-slate-500">' . self::e($m['email']) . '</p>

                            <p class="text-[11px] text-slate-400 font-mono">Doc: ' . self::e($m['cedula'] ?? '—') . '</p>

                        </div>

                    </div>

                </td>

                <td class="px-4 py-3.5 text-xs font-semibold text-slate-800">' . $ageDisplay . '</td>

                <td class="px-4 py-3.5">' . $docsHtml . '</td>

                <td class="px-4 py-3.5 text-xs">

                    ' . $statusBadge . '

                    ' . (!empty($m['approved_at']) ? '<div class="text-[10px] text-slate-400 mt-1">Habilitado el ' . date('d/m/Y', strtotime($m['approved_at'])) . '</div>' : '') . '

                </td>

                <td class="px-4 py-3.5 text-xs text-slate-500 font-mono">' . $dateFormatted . '</td>

                <td class="px-4 py-3.5 text-right whitespace-nowrap">' . $actionBtn . '</td>

            </tr>';

        }



        if (!$rows) {

            $rows = '<tr><td colspan="7" class="p-12 text-center text-sm text-slate-400">No hay aspirantes menores de edad registrados en este momento.</td></tr>';

        }



        return $metricCards . '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">

                <div>

                    <div class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-0.5 text-xs font-bold text-amber-800 border border-amber-200 mb-1">

                        ' . self::iconShield('w-3.5 h-3.5') . ' Módulo Legal de Menores

                    </div>

                    <h2 class="text-xl font-bold text-slate-900">Aspirantes Menores de Edad (16 - 17 años)</h2>

                    <p class="text-xs text-slate-500 mt-0.5">Supervisión y control de documentación exigida por ley. Puedes consultar los 3 documentos radicados y quitar el bloqueo para permitir la interacción del usuario.</p>

                </div>

                <div class="flex items-center gap-3">

                    <div class="relative w-full sm:w-72">

                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">' . self::iconSearch('w-4 h-4') . '</span>

                        <input id="minorSearchInput" onkeyup="filterMinorTable()" type="text" placeholder="Filtro rápido..." class="w-full rounded-xl border border-slate-200 pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c]">

                    </div>

                    <span class="whitespace-nowrap rounded-xl bg-slate-100 border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">' . count($minors) . ' aspirantes</span>

                </div>

            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-100">

                <table id="minorTable" class="w-full min-w-[950px] text-left text-sm">

                    <thead class="bg-slate-50/80 border-b border-slate-200/80">

                        <tr class="text-xs font-bold uppercase tracking-wider text-slate-500">

                            <th class="px-4 py-3">ID</th>

                            <th class="px-4 py-3">Aspirante</th>

                            <th class="px-4 py-3">Edad</th>

                            <th class="px-4 py-3">Documentos Obligatorios</th>

                            <th class="px-4 py-3">Estado Bloqueo</th>

                            <th class="px-4 py-3">Fecha Alta</th>

                            <th class="px-4 py-3 text-right">Acción</th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">' . $rows . '</tbody>

                </table>

            </div>

        </div>

        <script>

        function filterMinorTable() {

            var input = document.getElementById("minorSearchInput");

            var filter = input.value.toLowerCase();

            var rows = document.querySelectorAll(".minor-row");

            rows.forEach(function(row) {

                var text = row.textContent.toLowerCase();

                row.style.display = text.indexOf(filter) > -1 ? "" : "none";

            });

        }

        </script>';

    }


    public static function minorBlockedView(array $user, ?string $message, ?string $error, ?array $docs): string
    {

        $alerts = self::alerts($message, $error);

        $hasDocs = !empty($docs['civil_registry_path']) && !empty($docs['parent_id_path']) && !empty($docs['authorization_letter_path']);



        $statusBanner = '';

        if ($hasDocs) {

            $uploadedDate = date('d/m/Y H:i', strtotime($docs['uploaded_at']));

            $statusBanner = '

            <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-5 md:p-6 shadow-sm">

                <div class="flex items-start gap-4">

                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">

                        ' . self::iconCheck('w-6 h-6') . '

                    </div>

                    <div>

                        <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-300 mb-1">

                            Documentación Radicada

                        </div>

                        <h2 class="text-lg font-bold text-slate-900">Tus 3 documentos han sido enviados con éxito</h2>

                        <p class="text-sm text-slate-600 mt-1 leading-relaxed">

                            Fueron radicados el <strong>' . $uploadedDate . '</strong> y actualmente se encuentran en <strong>revisión por parte de la administración</strong>.

                            Tu flujo de interacción en el portal permanecerá en espera hasta que el administrador verifique los archivos y retire el bloqueo de tu cuenta.

                        </p>

                    </div>

                </div>

            </div>';

        }



        $docsListHtml = '';

        if ($hasDocs) {

            $docsListHtml = '

            <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">

                    ' . self::iconDocument('w-5 h-5 text-[#17457c]') . ' Documentos Adjuntados en tu Solicitud

                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-4">

                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-1">1. Registro Civil</span>

                        <p class="text-sm font-semibold text-slate-800 truncate" title="' . self::e($docs['civil_registry_name']) . '">' . self::e($docs['civil_registry_name']) . '</p>

                        <a href="?action=download_minor_doc&type=civil_registry" target="_blank" class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline">

                            ' . self::iconDownload('w-3.5 h-3.5') . ' Descargar archivo

                        </a>

                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-4">

                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-1">2. Documento de Padres</span>

                        <p class="text-sm font-semibold text-slate-800 truncate" title="' . self::e($docs['parent_id_name']) . '">' . self::e($docs['parent_id_name']) . '</p>

                        <a href="?action=download_minor_doc&type=parent_id" target="_blank" class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline">

                            ' . self::iconDownload('w-3.5 h-3.5') . ' Descargar archivo

                        </a>

                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-4">

                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-1">3. Carta de Autorización</span>

                        <p class="text-sm font-semibold text-slate-800 truncate" title="' . self::e($docs['authorization_letter_name']) . '">' . self::e($docs['authorization_letter_name']) . '</p>

                        <a href="?action=download_minor_doc&type=authorization_letter" target="_blank" class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline">

                            ' . self::iconDownload('w-3.5 h-3.5') . ' Descargar archivo

                        </a>

                    </div>

                </div>

            </div>';

        }



        $formTitle = $hasDocs

            ? '¿Deseas reemplazar o actualizar tus documentos?'

            : 'Sube los 3 documentos requeridos para solicitar el desbloqueo';



        $formDescription = $hasDocs

            ? 'Si te fue solicitado corregir o actualizar la documentación, puedes cargar nuevamente los 3 archivos a continuación:'

            : 'Para que la administración pueda autorizar tu interacción en el portal y habilitar tu postulación a vacantes, adjunta los 3 archivos solicitados por la ley:';



        $uploadForm = '

        <div class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

            <div class="mb-6">

                <h3 class="text-lg font-bold text-slate-900">' . $formTitle . '</h3>

                <p class="text-xs text-slate-500 mt-1">' . $formDescription . '</p>

            </div>

            <form method="post" action="?action=upload_minor_documents" enctype="multipart/form-data" class="space-y-6">

                <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">



                <div class="rounded-2xl border border-slate-200 p-5 bg-slate-50/50 hover:bg-slate-50 transition">

                    <label class="block text-sm font-bold text-slate-900 mb-1">

                        1. Copia legible del Registro Civil de Nacimiento del menor de edad <span class="text-rose-500">*</span>

                    </label>

                    <p class="text-xs text-slate-500 mb-3">Documento oficial que acredita tu parentesco y edad. Formato admitido: PDF, JPG, PNG (máximo 10 MB).</p>

                    <input type="file" name="minor_civil_registry" accept=".pdf,.jpg,.jpeg,.png" required

                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#17457c] file:text-white hover:file:bg-[#12365f] cursor-pointer">

                </div>



                <div class="rounded-2xl border border-slate-200 p-5 bg-slate-50/50 hover:bg-slate-50 transition">

                    <label class="block text-sm font-bold text-slate-900 mb-1">

                        2. Documento de identidad (Cédula de ciudadanía) de los padres o representantes legales <span class="text-rose-500">*</span>

                    </label>

                    <p class="text-xs text-slate-500 mb-3">Copia legible por ambas caras de la cédula del padre, madre o tutor legal. Formato admitido: PDF, JPG, PNG (máximo 10 MB).</p>

                    <input type="file" name="minor_parent_id" accept=".pdf,.jpg,.jpeg,.png" required

                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#17457c] file:text-white hover:file:bg-[#12365f] cursor-pointer">

                </div>



                <div class="rounded-2xl border border-slate-200 p-5 bg-slate-50/50 hover:bg-slate-50 transition">

                    <label class="block text-sm font-bold text-slate-900 mb-1">

                        3. Carta de autorización o formato específico firmado y autenticado según el trámite requerido <span class="text-rose-500">*</span>

                    </label>

                    <p class="text-xs text-slate-500 mb-3">Documento debidamente firmado y autenticado ante notaría o autoridad competente que autoriza la vinculación del menor. Formato admitido: PDF, DOC, DOCX, JPG, PNG (máximo 10 MB).</p>

                    <input type="file" name="minor_authorization" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required

                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#17457c] file:text-white hover:file:bg-[#12365f] cursor-pointer">

                </div>



                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">

                    <div class="text-xs text-slate-500 flex items-center gap-1.5">

                        ' . self::iconShield('w-4 h-4 text-emerald-600') . '

                        <span>Tus datos y documentos están protegidos bajo la ley de Habeas Data.</span>

                    </div>

                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#12365f] shadow-md">

                        ' . self::iconUpload('w-4 h-4') . ' ' . ($hasDocs ? 'Actualizar Documentos' : 'Radicar Documentos') . '

                    </button>

                </div>

            </form>

        </div>';



        return '

        <div class="mx-auto max-w-4xl py-8 px-4 sm:px-6">

            ' . $alerts . '

            <div class="mb-8 rounded-3xl border border-amber-200 bg-amber-50/60 p-6 md:p-8 shadow-sm">

                <div class="flex flex-col sm:flex-row items-start gap-4">

                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-md shadow-amber-500/20">

                        ' . self::iconLock('w-6 h-6') . '

                    </div>

                    <div class="flex-1">

                        <div class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-0.5 text-xs font-bold text-amber-800 border border-amber-300 mb-2">

                            ' . self::iconShield('w-3.5 h-3.5') . ' Cuenta en Verificación Legal Obligatoria

                        </div>

                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Validación de Menor de Edad (16 - 17 años)</h1>

                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">

                            Hola, <strong>' . self::e($user['name']) . '</strong>. De acuerdo con las disposiciones del Código de la Infancia y la Adolescencia y las normativas laborales de Colombia, el acceso e interacción en la plataforma para aspirantes menores de 18 años está restringido hasta que se adjunten y aprueben los 3 documentos de autorización legal correspondientes.

                        </p>

                    </div>

                </div>

            </div>



            ' . $statusBanner . '

            ' . $docsListHtml . '

            ' . $uploadForm . '



            <div class="mt-8 text-center">

                <form method="post" action="?action=logout" class="inline">

                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                    <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">

                        ' . self::iconArrowLeft('w-3.5 h-3.5') . ' Cerrar Sesión

                    </button>

                </form>

            </div>

        </div>';

    }


    /**

     * Bloque de captura y lectura del reverso del documento de identidad.

     *

     * Solo se usa en el registro de candidatos: las cuentas de empresa no

     * tienen ningún requisito de edad ni de documento escaneado.

     */

    public static function idScanSection(IdScanConfig $idScan): string
    {

        $title = $idScan->required

            ? 'Foto del reverso de tu cédula o TI <span class="text-rose-500">*</span>'

            : 'Foto del reverso de tu cédula o TI (opcional)';



        return '

        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4" id="idScanSection">

            <div class="flex items-start gap-3">

                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#17457c] text-white">

                    ' . self::iconCamera('w-5 h-5') . '

                </div>

                <div class="min-w-0 flex-1">

                    <h3 class="text-sm font-bold text-slate-900">' . $title . '</h3>

                    <p class="mt-1 text-xs leading-5 text-slate-500">

                        Adjunta una foto nítida, de frente y bien iluminada del reverso. Leemos el código de barras PDF417 (y, si no es posible, los caracteres inferiores)

                        para validar el documento y confirmar que tienes ' . self::e($idScan->ageRangeLabel()) . '.

                    </p>

                </div>

            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">

                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-[#17457c] hover:text-[#17457c]">

                    ' . self::iconUpload('w-4 h-4') . '

                    <span>Subir foto del reverso</span>

                    <input id="idScanImage" name="id_scan_image" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">

                </label>

                <button type="button" id="idScanCamera" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-[#17457c] hover:text-[#17457c]">

                    ' . self::iconCamera('w-4 h-4') . ' Usar la cámara

                </button>

            </div>

            <video id="idScanVideo" playsinline muted class="mt-3 hidden w-full rounded-xl border border-slate-200 bg-black"></video>

            <button type="button" id="idScanStop" hidden class="mt-2 text-xs font-semibold text-rose-600 hover:underline">Detener la cámara</button>

            <div class="mt-3 flex items-start gap-3">

                <img id="idScanPreview" alt="Vista previa del reverso" class="hidden h-24 w-36 flex-shrink-0 rounded-xl border border-slate-200 object-cover">

                <div class="min-w-0 flex-1">

                    <div id="idScanSummary" class="rounded-xl border border-slate-200 bg-white px-3 py-1"></div>

                    <p id="idScanStatus" class="text-xs font-semibold mt-2 text-slate-500"></p>

                </div>

            </div>

            <p class="mt-3 text-[11px] leading-4 text-slate-400">

                Solo se aceptan imágenes JPG, PNG o WEBP de hasta ' . round($idScan->maxSize / 1048576) . ' MB.

                La imagen se guarda en un almacenamiento privado y solo puede consultarla tu cuenta o la administración del portal.

            </p>

        </div>';

    }


    /**

     * Carga el lector de documentos del formulario de registro de candidatos.

     */

    public static function idScanScript(): string
    {

        return '

        <script type="module">

            import { initIdScanForm } from "' . self::assetUrl('id-reader/form.js') . '";

            initIdScanForm();

        </script>';

    }
}
