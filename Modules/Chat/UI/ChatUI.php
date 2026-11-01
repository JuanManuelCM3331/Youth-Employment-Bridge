<?php

namespace Modules\Chat\UI;

use App\Http\PortalViewRenderer;
use Modules\Dashboard\UI\DashboardUI;

final class ChatUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (conversaciones y mensajes).
     */
    public static function styles(): string
    {
        return '
        /* Entrada del contenido del chat */
        .candidate-page > * { animation: yeb-rise 0.3s cubic-bezier(0.22, 0.61, 0.36, 1) backwards; }

        /* Lista de conversaciones: cascada */
        .chat-conv-card { animation: yeb-rise 0.28s cubic-bezier(0.22, 0.61, 0.36, 1) backwards; }
        .chat-conv-card:nth-child(2) { animation-delay: 0.03s; }
        .chat-conv-card:nth-child(3) { animation-delay: 0.06s; }
        .chat-conv-card:nth-child(4) { animation-delay: 0.09s; }
        .chat-conv-card:nth-child(n+5) { animation-delay: 0.12s; }

        /* Burbujas: aparicion suave al abrir la conversacion */
        #chat-messages-container > div { animation: yeb-rise 0.22s ease-out backwards; }
        #chat-messages-container { overscroll-behavior: contain; }
        ';
    }

    public static function companyMessagesView(array $conversations, array $user): string
    {

        $cards = '';

        foreach ($conversations as $c) {

            $statusBadge = match ($c['status']) {

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

            <div class="chat-conv-card flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 rounded-2xl border border-slate-200/90 bg-white hover:border-[#17457c] hover:shadow-md transition group w-full" data-title="' . strtolower(self::e(($c['candidate_name'] ?? '') . ' ' . ($c['job_title'] ?? ''))) . '">

                <a href="?action=chat&application_id=' . $c['application_id'] . '" class="flex items-start gap-4 flex-1 min-w-0">

                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-bold text-lg border border-blue-100">

                        ' . self::e(mb_strtoupper(mb_substr($c['candidate_name'] ?? 'C', 0, 1, 'UTF-8'))) . '

                    </div>

                    <div class="flex-1 min-w-0">

                        <div class="flex flex-wrap items-center gap-2">

                            <h3 class="font-bold text-base text-slate-900 group-hover:text-[#17457c] transition truncate max-w-full">' . self::e($c['candidate_name'] ?? '') . '</h3>

                            ' . $statusBadge . '

                            ' . $unreadBadge . '

                        </div>

                        <p class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-2 truncate">

                            <span>' . self::e($c['candidate_email'] ?? '') . '</span>

                            <span>·</span>

                            <span class="font-semibold text-slate-700">Vacante: ' . self::e($c['job_title'] ?? '') . '</span>

                        </p>

                        <p class="text-xs text-slate-600 mt-2 truncate block">' . $lastMsg . '</p>

                    </div>

                </a>

                <div class="flex md:flex-col items-center md:items-end justify-between gap-2 text-right flex-shrink-0 md:min-w-[140px]">

                    <span class="text-[11px] text-slate-400 whitespace-nowrap">' . $timeStr . '</span>

                    <a href="?action=candidate_public_profile&candidate=' . (int) $c['candidate_id'] . '" class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-[#17457c] transition whitespace-nowrap">

                        Ver perfil ' . self::iconUser('w-3.5 h-3.5') . '

                    </a>

                    <a href="?action=chat&application_id=' . $c['application_id'] . '" class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] group-hover:translate-x-0.5 transition whitespace-nowrap">

                        Abrir chat ' . self::iconArrowRight('w-3.5 h-3.5') . '

                    </a>

                </div>

            </div>';

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

                    <div class="flex items-center gap-3">

                        <div class="relative w-full sm:w-64">

                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">

                                ' . self::iconSearch('w-4 h-4') . '

                            </span>

                            <input type="text" id="chatSearchInput" placeholder="Buscar candidato o vacante..." class="w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] shadow-sm transition">

                        </div>

                        <span class="rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700 whitespace-nowrap self-start sm:self-auto">' . count($conversations) . ' chat(s)</span>

                    </div>

                </div>

                <div class="space-y-3.5">' . ($cards ?: '<div class="py-16 text-center text-sm text-slate-400">No hay postulaciones ni conversaciones registradas aún.</div>') . '</div>

            </article>

        </div>';

    }


    public static function candidateMessages(array $user, array $conversations, ?string $message, ?string $error): string
    {

        $cards = '';

        foreach ($conversations as $c) {

            $statusBadge = match ($c['status'] ?? '') {

                'accepted' => '<span class="rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold whitespace-nowrap">Aceptado</span>',

                'interview' => '<span class="rounded-full bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-0.5 text-xs font-bold whitespace-nowrap">Entrevista</span>',

                'review' => '<span class="rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 text-xs font-bold whitespace-nowrap">En revisión</span>',

                'rejected' => '<span class="rounded-full bg-rose-50 text-rose-700 border border-rose-200 px-2.5 py-0.5 text-xs font-bold whitespace-nowrap">Rechazado</span>',

                default => '<span class="rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 text-xs font-bold whitespace-nowrap">Pendiente</span>'

            };



            $lastMsg = !empty($c['last_message'])

                ? self::e(mb_strimwidth($c['last_message'], 0, 90, '...'))

                : '<span class="italic text-slate-400">Sin mensajes todavía. ¡Haz clic para iniciar la conversación!</span>';



            $unreadBadge = (!empty($c['unread_count']) && (int) $c['unread_count'] > 0)

                ? '<span class="rounded-full bg-blue-600 text-white text-[10px] font-bold px-2 py-0.5 whitespace-nowrap shadow-sm">' . (int) $c['unread_count'] . ' nuevo(s)</span>'

                : '';



            $timeStr = !empty($c['last_message_at'])

                ? date('d/m/Y H:i', strtotime($c['last_message_at']))

                : (!empty($c['applied_at']) ? date('d/m/Y', strtotime($c['applied_at'])) : '');



            $companyName = (string) ($c['company_name'] ?? 'Empresa');

            $jobTitle = (string) ($c['job_title'] ?? 'Vacante');

            $searchData = strtolower(self::e($companyName . ' ' . $jobTitle));



            $cards .= '

            <a href="?action=chat&application_id=' . (int) $c['application_id'] . '" class="chat-conv-card flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl border border-slate-200/90 bg-white hover:border-[#17457c] hover:shadow-md transition group w-full" data-title="' . $searchData . '">

                <div class="flex items-start gap-4 flex-1 min-w-0">

                    <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-bold text-lg border border-blue-100 shadow-sm">

                        ' . self::e(mb_strtoupper(mb_substr($companyName, 0, 1, 'UTF-8'))) . '

                    </div>

                    <div class="flex-1 min-w-0">

                        <div class="flex flex-wrap items-center gap-2">

                            <h3 class="font-bold text-base text-slate-900 group-hover:text-[#17457c] transition truncate max-w-full">' . self::e($companyName) . '</h3>

                            ' . $statusBadge . '

                            ' . $unreadBadge . '

                        </div>

                        <p class="text-xs font-medium text-slate-500 mt-0.5 truncate">Vacante: <span class="font-bold text-slate-700">' . self::e($jobTitle) . '</span></p>

                        <p class="text-xs text-slate-600 mt-2 truncate block">' . $lastMsg . '</p>

                    </div>

                </div>

                <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-1.5 text-right flex-shrink-0 sm:min-w-[130px]">

                    <span class="text-[11px] text-slate-400 whitespace-nowrap">' . $timeStr . '</span>

                    <span class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] group-hover:translate-x-0.5 transition whitespace-nowrap">

                        Ver chat ' . self::iconArrowRight('w-3.5 h-3.5') . '

                    </span>

                </div>

            </a>';

        }



        $searchScript = '

        <script>

        document.addEventListener("DOMContentLoaded", function () {

            var searchInput = document.getElementById("chatSearchInput");

            if (!searchInput) return;

            searchInput.addEventListener("input", function () {

                var query = this.value.toLowerCase().trim();

                var cards = document.querySelectorAll(".chat-conv-card");

                var visibleCount = 0;

                cards.forEach(function (card) {

                    var title = card.getAttribute("data-title") || "";

                    if (query === "" || title.indexOf(query) !== -1) {

                        card.style.display = "";

                        visibleCount++;

                    } else {

                        card.style.display = "none";

                    }

                });

                var emptyMsg = document.getElementById("chatEmptySearch");

                if (emptyMsg) {

                    emptyMsg.style.display = (visibleCount === 0 && query !== "") ? "block" : "none";

                }

            });

        });

        </script>';



        return self::alerts($message, $error) . '

        <section class="candidate-page">

            ' . DashboardUI::candidateNav('messages') . '

            <div class="max-w-4xl mx-auto">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">

                    <div>

                        <h1 class="text-2xl font-bold text-slate-900">Mensajes con Empresas</h1>

                        <p class="text-xs text-slate-500 mt-1">Comunícate directamente con los empleadores de las ofertas a las que te has postulado.</p>

                    </div>

                    <div class="flex items-center gap-3">

                        <div class="relative w-full sm:w-64">

                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">

                                ' . self::iconSearch('w-4 h-4') . '

                            </span>

                            <input type="text" id="chatSearchInput" placeholder="Buscar empresa o vacante..." class="w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] shadow-sm transition">

                        </div>

                        <span class="rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700 whitespace-nowrap">' . count($conversations) . ' chat(s)</span>

                    </div>

                </div>

                <div class="space-y-3.5">' . ($cards ?: '<div class="profile-card p-12 text-center text-slate-400">No tienes conversaciones activas aún. Aplica a ofertas de empleo para poder chatear con las empresas.</div>') . '

                    <div id="chatEmptySearch" style="display:none;" class="profile-card p-8 text-center text-slate-400">

                        No se encontraron conversaciones que coincidan con la búsqueda.

                    </div>

                </div>

            </div>

            ' . $searchScript . '

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



        $statusConfig = match ($application['status']) {

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



        // Cierre de chat cuando la postulación entra en aceptada o rechazada
        if ($application['status'] === 'accepted') {
            $inputArea = '
            <div class="rounded-2xl border-2 border-emerald-200 bg-emerald-50/90 p-5 text-center shadow-sm">
                <div class="inline-flex items-center justify-center gap-2 text-emerald-800 font-bold text-sm">
                    ' . self::iconCheck('w-5 h-5 text-emerald-600 flex-shrink-0') . '
                    <span>Postulación Aceptada — Chat Cerrado</span>
                </div>
                <p class="text-xs text-emerald-700 mt-1.5 max-w-lg mx-auto">
                    El proceso de selección ha finalizado con estado <strong>Aceptado</strong>. El canal de comunicación se encuentra cerrado y ya no es posible enviar nuevos mensajes.
                </p>
            </div>';
        } elseif (!$canSend || $application['status'] === 'rejected') {

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
}
