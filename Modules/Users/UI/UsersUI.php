<?php

namespace Modules\Users\UI;

use App\Http\PortalViewRenderer;
use Modules\Dashboard\UI\DashboardUI;
use Modules\Users\Controllers\UserController;

final class UsersUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (usuarios y hoja de vida).
     */
    public static function styles(): string
    {
        return '
        /* Tabla de usuarios: filas alternas y aparicion escalonada */
        :where(#userTable) tbody tr:nth-child(even) { background-color: #fbfcfe; }
        :where(#userTable) tbody tr:nth-child(-n+10) { animation: yeb-row-in 0.24s ease-out backwards; }
        :where(#userTable) tbody tr:nth-child(2) { animation-delay: 0.03s; }
        :where(#userTable) tbody tr:nth-child(3) { animation-delay: 0.06s; }
        :where(#userTable) tbody tr:nth-child(4) { animation-delay: 0.09s; }
        :where(#userTable) tbody tr:nth-child(n+5) { animation-delay: 0.12s; }
        ';
    }

    public static function adminUsersView(array $users, array $currentUser): string
    {

        $rows = '';

        foreach ($users as $u) {

            $isSelf = ((int) $u['id'] === (int) $currentUser['id']);

            $status = $u['status'] ?? 'active';



            $roleBadge = match ($u['role']) {

                'admin' => '<span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200">' . self::iconShield('w-3 h-3') . ' Administrador</span>',

                'recruiter' => '<span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 border border-indigo-200">' . self::iconBuilding('w-3 h-3') . ' Empresa</span>',

                default => '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">' . self::iconUser('w-3 h-3') . ' Candidato</span>'

            };



            $statusBadge = $status === 'suspended'

                ? '<span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 border border-rose-200">Suspendido</span>'

                : '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">Activo</span>';



            $actionBtn = $isSelf

                ? '<span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">Sesión actual</span>'

                : '<div class="flex items-center justify-end gap-2">

                    ' . ($status === 'suspended'

                    ? '<form method="post" action="?action=admin_activate_user" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="user_id" value="' . $u['id'] . '"><button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 hover:border-emerald-300 transition shadow-sm">Activar</button></form>'

                    : '<form method="post" action="?action=admin_suspend_user" class="inline" onsubmit="return confirm(\'¿Estás seguro de suspender al usuario \\\' ' . addslashes(self::e($u['name'])) . ' \\\'? No podrá iniciar sesión.\')"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="user_id" value="' . $u['id'] . '"><button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-100 hover:border-amber-300 transition shadow-sm">Suspender</button></form>'

                ) . '

                    <form method="post" action="?action=admin_delete_user" class="inline" onsubmit="return confirm(\'¿Estás seguro de eliminar al usuario \\\' ' . addslashes(self::e($u['name'])) . ' \\\'? Esta acción eliminará permanentemente su cuenta, postulaciones y registros vinculados.\')">

                        <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                        <input type="hidden" name="user_id" value="' . $u['id'] . '">

                        <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 hover:border-rose-300 transition shadow-sm">

                            ' . self::iconTrash('w-3.5 h-3.5') . ' Eliminar

                        </button>

                    </form>

                </div>';



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

                <td class="px-4 py-3.5 text-xs">' . $statusBadge . '</td>

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

                <table id="userTable" class="w-full min-w-[950px] text-left text-sm">

                    <thead class="bg-slate-50/80 border-b border-slate-200/80">

                        <tr class="text-xs font-bold uppercase tracking-wider text-slate-500">

                            <th class="px-4 py-3">ID</th>

                            <th class="px-4 py-3">Usuario</th>

                            <th class="px-4 py-3">Rol</th>

                            <th class="px-4 py-3">Estado</th>

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


    public static function adminCreateAdminView(array $users): string
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


    public static function candidateProfile(array $user, ?string $message, ?string $error, UserController $userController): string
    {

        $profile = $userController->profile($user['id']);

        if (!$profile)
            return self::alerts($message, 'No se encontró el perfil del usuario.');

        $hasCv = !empty($profile['cv_bytes']) || (!empty($profile['cv_path']) && !str_starts_with((string) $profile['cv_path'], 'db://') && is_file($profile['cv_path']));
        $cv = $hasCv

            ? '<div class="flex flex-wrap items-center gap-2 text-emerald-700 font-medium text-sm">' . self::iconCheck('w-4 h-4 shrink-0') . '<span class="min-w-0 break-all">' . self::e($profile['cv_original_name']) . '</span> · <a class="font-bold underline" href="?action=download_cv&candidate=' . $user['id'] . '">Descargar</a> · <form method="post" action="?action=delete_cv" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><button class="font-bold text-rose-600 hover:underline" onclick="return confirm(\'¿Eliminar tu hoja de vida?\')">Eliminar</button></form></div>'

            : '<p class="text-slate-500 text-sm">No has cargado una hoja de vida.</p>';

        $idScan = !empty($profile['id_scan_path'])

            ? '<div class="flex flex-wrap items-center gap-2 text-emerald-700 font-medium text-sm mt-3">' . self::iconCheck('w-4 h-4') . '<span>Reverso de tu documento verificado</span> · <a class="font-bold underline" href="?action=download_id_scan&candidate=' . $user['id'] . '">Ver imagen</a></div>'

            : '';

        $hasPhoto = !empty($profile['profile_photo_bytes']) || (!empty($profile['profile_photo_path']) && !str_starts_with((string) $profile['profile_photo_path'], 'db://') && is_file($profile['profile_photo_path']));
        $photo = $hasPhoto

            ? '<img src="?action=download_profile_photo&type=user&id=' . $user['id'] . '" alt="Foto" class="profile-avatar">'

            : '<div class="profile-avatar profile-avatar-empty">' . strtoupper(substr($profile['name'], 0, 1)) . '</div>';

        $form = '<form method="post" action="?action=save_profile" enctype="multipart/form-data" class="profile-form"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . self::fieldValue('name', 'Nombre completo', $profile['name']) . self::fieldValue('professional_title', 'Titular profesional', $profile['professional_title']) . self::fieldValue('phone', 'Teléfono', $profile['phone']) . self::fieldValue('location', 'Ubicación', $profile['location']) . self::fieldValue('availability', 'Disponibilidad', $profile['availability']) . self::fieldValue('linkedin_url', 'LinkedIn', $profile['linkedin_url'], 'url') . self::fieldValue('portfolio_url', 'Portafolio', $profile['portfolio_url'], 'url') . self::fieldValue('skills', 'Conocimientos y habilidades', $profile['skills'], 'textarea') . self::fieldValue('experience', 'Experiencia profesional', $profile['experience'], 'textarea') . self::fieldValue('education', 'Educación', $profile['education'], 'textarea') . self::fieldValue('bio', 'Resumen profesional', $profile['bio'], 'textarea') . '<div class="md:col-span-2 min-w-0"><label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Foto de perfil<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="w-full max-w-full min-w-0 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#17457c] hover:file:bg-blue-100"></label></div><div class="md:col-span-2 flex justify-end"><button class="primary-button w-full sm:w-auto text-center">Guardar cambios</button></div></form>';

        return self::alerts($message, $error) . '<section class="candidate-page">' . DashboardUI::candidateNav('profile') . '<h1 class="page-title">Hoja de Vida</h1><div class="profile-layout"><div class="min-w-0"><article class="profile-card profile-identity border-t-4 border-t-[#17457c] overflow-hidden"><div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-5 min-w-0">' . $photo . '<div class="min-w-0 break-words"><h2>' . self::e($profile['name']) . '</h2><p>' . self::e($profile['location'] ?: 'Agrega tu ubicación') . '</p><p>' . self::e($profile['phone'] ?: 'Agrega tu teléfono') . '</p></div></div><div class="mt-6 sm:mt-8 min-w-0">' . $form . '</div></article><article class="profile-card mt-6 border-t-4 border-t-emerald-600 overflow-hidden"><h2>Documentos adjuntos</h2><div class="mt-4 min-w-0">' . $cv . $idScan . '</div><form method="post" action="?action=upload_cv" enctype="multipart/form-data" class="mt-5 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 min-w-0"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input required type="file" name="cv" accept=".pdf,.doc,.docx" class="min-w-0 w-full max-w-full flex-1 rounded-xl border border-slate-200 p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-emerald-700 hover:file:bg-emerald-100"><button class="primary-button !py-2.5 !bg-emerald-600 hover:!bg-emerald-700 w-full sm:w-auto text-center shrink-0">Subir CV</button></form></article><article class="profile-card mt-6 border-t-4 border-t-amber-500 overflow-hidden"><h2>Seguridad de la cuenta</h2><p class="mt-1 text-sm text-slate-500">Actualiza tu contraseña para proteger tu perfil, tu hoja de vida y tus postulaciones.</p>' . self::passwordChangeForm() . '</article></div><aside class="profile-card side-card border-t-4 border-t-indigo-600 min-w-0"><h2>Tu perfil profesional</h2><p class="mt-2 text-sm text-slate-500">Una información completa y detallada aumenta las posibilidades de ser contactado por reclutadores.</p><a href="?action=dashboard" class="mt-4 inline-flex items-center gap-1 font-bold text-sm text-[#17457c]">Volver a Mi área ' . self::iconArrowRight('w-3.5 h-3.5') . '</a></aside></div></section>' . self::passwordMatchScript();

    }


    /**

     * Perfil profesional de un candidato visto por la empresa que tiene su postulación.

     *

     * Nunca se muestra el número de documento ni la imagen del reverso de la

     * cédula: ese material es privado del titular y de la administración.

     */

    public static function candidatePublicProfile(array $profile, ?array $user, ?string $message, ?string $error): string
    {

        $hasPhoto = !empty($profile['profile_photo_bytes']) || (!empty($profile['profile_photo_path']) && !str_starts_with((string) $profile['profile_photo_path'], 'db://') && is_file($profile['profile_photo_path']));
        $photo = $hasPhoto

            ? '<img src="?action=download_profile_photo&type=user&id=' . (int) $profile['id'] . '" alt="Foto de perfil" class="h-20 w-20 rounded-2xl object-cover border-2 border-slate-200 shadow-sm">'

            : '<div class="h-20 w-20 rounded-2xl flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-extrabold text-2xl border border-blue-100">' . strtoupper(substr((string) $profile['name'], 0, 1)) . '</div>';



        $hasCv = !empty($profile['cv_bytes']) || (!empty($profile['cv_path']) && !str_starts_with((string) $profile['cv_path'], 'db://') && is_file($profile['cv_path']));
        $cv = $hasCv

            ? '<a class="inline-flex items-center gap-1.5 rounded-xl bg-[#17457c] px-4 py-2.5 text-xs font-bold text-white transition hover:bg-[#12365f] shadow-sm" href="?action=download_cv&candidate=' . (int) $profile['id'] . '">' . self::iconDownload('w-4 h-4') . ' Descargar Hoja de Vida</a>'

            : '<span class="text-xs text-slate-400 font-medium bg-slate-100 px-2.5 py-1.5 rounded-lg">Sin CV adjunto</span>';



        $links = '';

        if (!empty($profile['linkedin_url'])) {

            $links .= '<a class="inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline" href="' . self::e($profile['linkedin_url']) . '" target="_blank" rel="noopener noreferrer">LinkedIn</a>';

        }

        if (!empty($profile['portfolio_url'])) {

            $links .= '<a class="inline-flex items-center gap-1.5 text-xs font-bold text-[#17457c] hover:underline" href="' . self::e($profile['portfolio_url']) . '" target="_blank" rel="noopener noreferrer">Portafolio</a>';

        }



        $facts = self::publicFact('Titular profesional', $profile['professional_title'] ?? null)

            . self::publicFact('Ubicación', $profile['location'] ?? null)

            . self::publicFact('Disponibilidad', $profile['availability'] ?? null)

            . self::publicFact('Correo', $profile['email'] ?? null)

            . self::publicFact('Teléfono', $profile['phone'] ?? null);



        return self::alerts($message, $error) . '

        <div class="max-w-4xl mx-auto pb-10">

            <a href="?action=company_applications" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-[#17457c] mb-5 transition">

                ' . self::iconArrowLeft('w-3.5 h-3.5') . ' Volver a los candidatos

            </a>

            <article class="rounded-3xl border border-slate-200/90 bg-white p-6 md:p-8 shadow-sm">

                <div class="flex flex-col md:flex-row md:items-center gap-5 pb-6 border-b border-slate-100">

                    ' . $photo . '

                    <div class="flex-1">

                        <div class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-0.5 text-xs font-bold text-[#17457c] border border-blue-200 mb-1.5">

                            ' . self::iconUser('w-3.5 h-3.5') . ' Candidato postulado

                        </div>

                        <h1 class="text-2xl font-extrabold text-slate-900">' . self::e($profile['name']) . '</h1>

                        <p class="text-sm text-slate-500 mt-1">' . self::e($profile['professional_title'] ?: 'Sin titular profesional registrado') . '</p>

                    </div>

                    <div class="flex items-center gap-2">' . $cv . '</div>

                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">' . $facts . '</div>

                ' . ($links ? '<div class="mt-5 flex flex-wrap items-center gap-4">' . $links . '</div>' : '') . '

            </article>

            <div class="mt-6 space-y-6">

                ' . self::publicSection('Resumen profesional', $profile['bio'] ?? null) . '

                ' . self::publicSection('Conocimientos y habilidades', $profile['skills'] ?? null) . '

                ' . self::publicSection('Experiencia profesional', $profile['experience'] ?? null) . '

                ' . self::publicSection('Educacion', $profile['education'] ?? null) . '

            </div>

            <p class="mt-6 flex items-start gap-2 text-xs text-slate-400">

                ' . self::iconShield('w-4 h-4') . ' El numero de documento y la imagen del reverso de la cedula son datos privados: no se comparten con la empresa.

            </p>

        </div>';

    }
}
