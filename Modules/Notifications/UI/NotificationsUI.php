<?php

namespace Modules\Notifications\UI;

use App\Http\PortalViewRenderer;
use Modules\Companies\UI\CompaniesUI;
use Modules\Dashboard\UI\DashboardUI;
use Modules\Notifications\Controllers\NotificationsController;

final class NotificationsUI extends PortalViewRenderer
{
    /**
     * Estilos y animaciones propios del modulo (alertas y notificaciones).
     */
    public static function styles(): string
    {
        return '
        /* Alertas sin leer: marca visual a la izquierda */
        .candidate-page:has(#push-status-badge) .application-list .application-card:not(.opacity-60) {
            border-left: 4px solid #17457c;
        }
        ';
    }

    /**

     * Widget de configuración y estado de notificaciones push.

     */

    public static function pushNotificationPanel(): string
    {

        return '

        <div class="mb-6 rounded-2xl border border-slate-200/90 bg-gradient-to-r from-blue-50/60 via-indigo-50/40 to-white p-5 shadow-sm">

            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">

                <div class="flex items-start gap-3.5">

                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-[#17457c] text-white shadow-sm">

                        ' . self::iconBell('w-6 h-6') . '

                    </div>

                    <div>

                        <div class="flex items-center gap-2.5 flex-wrap">

                            <h2 class="text-sm font-bold text-slate-900">Notificaciones Push</h2>

                            <span id="push-status-badge" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">

                                <span class="h-2 w-2 rounded-full bg-amber-500"></span> Comprobando estado...

                            </span>

                        </div>

                        <p class="text-xs text-slate-500 mt-1 max-w-xl">

                            Recibe alertas instantáneas en tu dispositivo sobre nuevas postulaciones, cambios de estado y mensajes de chat en tiempo real.

                        </p>

                    </div>

                </div>

                <div class="flex flex-wrap items-center gap-2 self-end sm:self-center w-full sm:w-auto">

                    <button type="button" id="btn-enable-push" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#17457c] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#12365f] shadow-sm">

                        Activar notificaciones push

                    </button>

                    <button type="button" id="btn-disable-push" style="display:none;" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 px-3.5 py-2 text-xs font-bold transition shadow-sm" title="Dejar de recibir notificaciones en este dispositivo">

                        Cancelar suscripción

                    </button>

                    <button type="button" id="btn-test-push" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 shadow-sm" title="Probar cómo llega una notificación">

                        Probar push

                    </button>

                </div>

            </div>

        </div>';

    }


    public static function candidateAlerts(array $user, ?string $message, ?string $error, NotificationsController $notificationsController): string
    {

        $items = $notificationsController->forUser($user['id']);

        $rows = '';

        foreach ($items as $item) {

            $isRead = !empty($item['read_at']);

            $markBtn = !$isRead ? '<form method="post" action="?action=mark_notification_read" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="notification_id" value="' . $item['id'] . '"><button class="text-xs font-semibold text-[#17457c] hover:underline">Marcar leída</button></form>' : '<span class="text-xs text-slate-400">Leída</span>';

            $titleHtml = !empty($item['title']) ? '<h3 class="text-sm font-bold text-slate-800 mb-0.5">' . self::e($item['title']) . '</h3>' : '';

            $linkHtml = !empty($item['url']) ? '<a href="' . self::e($item['url']) . '" class="inline-flex items-center gap-1 text-xs font-semibold text-[#17457c] hover:underline mt-1.5">Ver detalles ' . self::iconArrowRight('w-3.5 h-3.5') . '</a>' : '';

            $rows .= '<article class="application-card' . ($isRead ? ' opacity-60' : '') . '"><div>' . $titleHtml . '<p class="text-xs sm:text-sm text-slate-700">' . self::e($item['message']) . '</p>' . $linkHtml . '<p class="text-[11px] text-slate-400 mt-1">' . self::e($item['created_at']) . '</p></div>' . $markBtn . '</article>';

        }

        $markAllBtn = $items ? '<form method="post" action="?action=mark_all_notifications_read" class="mb-4 text-right"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><button class="text-sm font-semibold text-[#17457c] hover:underline">Marcar todas como leídas</button></form>' : '';

        return self::alerts($message, $error) . '<section class="candidate-page">' . DashboardUI::candidateNav('alerts') . '<h1 class="page-title">Mis alertas</h1>' . self::pushNotificationPanel() . $markAllBtn . '<div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 mb-3">' . self::iconBell('w-7 h-7 text-slate-400') . '</div><h2>No tienes alertas todavía</h2><p class="text-slate-500 text-sm">Recibirás notificaciones cuando haya cambios en tus postulaciones.</p></div>') . '</div></section>';

    }


    /**

     * Sección de alertas/notificaciones del panel de empresa.

     */

    public static function companyAlerts(array $user, ?string $message, ?string $error, NotificationsController $notificationsController): string
    {

        $items = $notificationsController->forUser($user['id']);

        $rows = '';

        foreach ($items as $item) {

            $isRead = !empty($item['read_at']);

            $markBtn = !$isRead ? '<form method="post" action="?action=mark_notification_read" class="inline"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="notification_id" value="' . $item['id'] . '"><button class="text-xs font-semibold text-[#17457c] hover:underline">Marcar leída</button></form>' : '<span class="text-xs text-slate-400">Leída</span>';

            $titleHtml = !empty($item['title']) ? '<h3 class="text-sm font-bold text-slate-800 mb-0.5">' . self::e($item['title']) . '</h3>' : '';

            $linkHtml = !empty($item['url']) ? '<a href="' . self::e($item['url']) . '" class="inline-flex items-center gap-1 text-xs font-semibold text-[#17457c] hover:underline mt-1.5">Ver detalles ' . self::iconArrowRight('w-3.5 h-3.5') . '</a>' : '';

            $rows .= '<article class="application-card' . ($isRead ? ' opacity-60' : '') . '"><div>' . $titleHtml . '<p class="text-xs sm:text-sm text-slate-700">' . self::e($item['message']) . '</p>' . $linkHtml . '<p class="text-[11px] text-slate-400 mt-1">' . self::e($item['created_at']) . '</p></div>' . $markBtn . '</article>';

        }

        $markAllBtn = $items ? '<form method="post" action="?action=mark_all_notifications_read" class="mb-4 text-right"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><button class="text-sm font-semibold text-[#17457c] hover:underline">Marcar todas como leídas</button></form>' : '';

        return self::alerts($message, $error) . '<section class="candidate-page company-page">' . CompaniesUI::companyNav('alerts') . '<h1 class="page-title">Mis alertas</h1>' . self::pushNotificationPanel() . $markAllBtn . '<div class="application-list">' . ($rows ?: '<div class="profile-card empty-state"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 mb-3">' . self::iconBell('w-7 h-7 text-slate-400') . '</div><h2>No tienes alertas todavía</h2><p class="text-slate-500 text-sm">Recibirás notificaciones cuando un candidato te escriba o postule a tus vacantes.</p></div>') . '</div></section>';

    }
}
