<?php

namespace App\Http;

use Modules\Identity\Dto\IdScanConfig;
use Modules\Identity\UI\IdentityUI;

/**
 * Shell compartido del portal: layout global, paginas publicas (home/legal),
 * pantalla de acceso denegado y primitivas de UI (avisos, campos, iconos).
 * Las vistas de cada modulo viven en Modules/<Modulo>/UI/<Modulo>UI.php.
 */
abstract class PortalViewRenderer
{
    /**
     * Hojas de estilo aportadas por cada modulo al renderizarse su vista.
     * Cada Modules/<Modulo>/UI/<Modulo>UI.php define su propio CSS en styles().
     *
     * @var array<string, string>
     */
    private static array $moduleStyles = [];

    /**
     * Registra el CSS de un modulo evitando duplicados en la misma respuesta.
     */
    protected static function enqueueModuleStyles(string $css): void
    {
        $css = trim($css);

        if ($css === '') {
            return;
        }

        self::$moduleStyles[md5($css)] = $css;
    }

    /**
     * Recoge el CSS de todos los modulos que participaron en la respuesta.
     */
    protected static function moduleStylesHtml(): string
    {
        $styles = [];

        foreach (get_declared_classes() as $class) {
            if ($class === self::class || !is_subclass_of($class, self::class)) {
                continue;
            }

            if (!method_exists($class, 'styles')) {
                continue;
            }

            $css = $class::styles();

            if (!is_string($css) || trim($css) === '') {
                continue;
            }

            $styles[md5($css)] = trim($css);
        }

        foreach (self::$moduleStyles as $key => $css) {
            $styles[$key] = $css;
        }

        if ($styles === []) {
            return '';
        }

        return '<style>' . implode("\n", $styles) . '</style>';
    }

    public static function layout(string $content, ?array $user, int $notificationCount = 0, int $chatUnreadCount = 0): string
    {

        $navLinks = [];

        $desktopLinksHtml = '';

        $mobileLinksHtml = '';

        $centerHeaderNav = false;



        if ($user) {

            $isBlockedMinor = (int) ($user['is_minor'] ?? 0) === 1 && (int) ($user['minor_blocked'] ?? 0) === 1;

            $currentAction = $_GET['action'] ?? 'home';

            $centerHeaderNav = in_array($user['role'], ['admin', 'candidate', 'recruiter'], true);

            if ($isBlockedMinor) {

                $navLinks = [];

            } elseif ($user['role'] === 'candidate') {

                $navLinks = [

                    ['action' => 'dashboard', 'label' => 'Mi área', 'href' => '?action=dashboard'],

                    ['action' => 'search', 'label' => 'Buscar empleos', 'href' => '?action=search'],

                ];

            } elseif ($user['role'] === 'recruiter') {

                $navLinks = [

                    ['action' => 'dashboard', 'label' => 'Resumen', 'href' => '?action=dashboard'],

                    ['action' => 'home', 'label' => 'Ver portal', 'href' => './'],

                ];

            } elseif ($user['role'] === 'admin') {
                $navLinks = [
                    ['action' => 'dashboard', 'tab' => 'metrics', 'label' => 'Métricas Graficadas', 'href' => '?action=dashboard&tab=metrics'],
                    ['action' => 'dashboard', 'tab' => 'audit', 'label' => 'Auditoría', 'href' => '?action=dashboard&tab=audit'],
                    ['action' => 'home', 'tab' => '', 'label' => 'Ver portal', 'href' => './'],
                ];

            }



            if (!empty($navLinks)) {

                $desktopLinksHtml = '<nav class="hidden md:flex shrink-0 items-center gap-1.5 lg:gap-2">';

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



            $roleLabel = match ($user['role']) {

                'recruiter' => 'Empresa',

                'admin' => 'Administrador',

                default => 'Candidato'

            };



            $chatBadge = ($chatUnreadCount > 0)

                ? '<span class="absolute -top-1 -right-1 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white leading-none shadow-sm">' . $chatUnreadCount . '</span>'

                : '';

            $messagesUrl = ($user['role'] === 'candidate') ? '?action=messages' : '?action=company_messages';



            if ($isBlockedMinor) {

                $rightNav = '

                <div class="' . ($centerHeaderNav ? 'flex-1 justify-end' : '') . ' flex items-center gap-2.5">

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 border border-amber-300">

                        <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>

                        Menor de edad (Bloqueado)

                    </span>

                    <div class="hidden sm:flex flex-col text-right leading-tight">

                        <span class="font-bold text-sm text-slate-900 max-w-[140px] truncate">' . self::e($user['name']) . '</span>

                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Verificación pendiente</span>

                    </div>

                    <form method="post" action="?action=logout" class="inline">

                        <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                        <button class="rounded-xl border border-slate-200 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 px-3.5 py-2 text-xs font-semibold text-slate-600 transition shadow-sm">Salir</button>

                    </form>

                </div>';

            } else {

                $rightNav = '

                <div class="' . ($centerHeaderNav ? 'flex-1 justify-end' : '') . ' flex items-center gap-2.5">

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

            }

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

                <div class="' . ($centerHeaderNav ? 'flex-1 min-w-0 ' : '') . 'flex items-center gap-6">

                    <a href="./" class="flex items-center gap-2 group">

                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white shadow-md shadow-blue-900/10">

                            ' . self::iconBriefcase('w-5 h-5 text-white') . '

                        </span>

                        <div>

                            <span class="display text-xl font-extrabold tracking-tight text-[#17457c] block leading-none">YEB<span class="text-amber-500">.</span></span>

                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block mt-0.5">Youth Employment Bridge</span>

                        </div>

                    </a>

                    ' . ($centerHeaderNav ? '' : $desktopLinksHtml) . '

                </div>

                ' . ($centerHeaderNav ? $desktopLinksHtml : '') . '

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

        /* Pestañas de panel (admin y empresa): mismo comportamiento que el panel del candidato */

        .admin-tabs a, .company-tabs a { color: #64748b; }

        .admin-tabs a:hover, .company-tabs a:hover { background: transparent; color: #1e293b; }

        .admin-tabs a.text-white, .company-tabs a.text-white { color: #fff; }

        .admin-tabs a.text-white:hover, .company-tabs a.text-white:hover { background: #17457c; color: #fff; }

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

        /* =========================================================================
           Mejoras de calidad de vida (solo estilos y navegación)
           No altera formularios, enlaces ni acciones existentes.
           ========================================================================= */

        /* Desplazamiento suave y espacio para el header fijo */
        html { scroll-behavior: smooth; scroll-padding-top: 92px; }

        :where(h1, h2, h3, section, article) { scroll-margin-top: 92px; }

        /* Selección de texto acorde a la marca */
        ::selection { background: rgba(23, 69, 124, 0.92); color: #fff; }

        /* Foco visible solo al navegar con teclado */
        :where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
            outline: 2px solid #17457c;
            outline-offset: 2px;
            border-radius: 0.5rem;
        }

        /* Barra de desplazamiento discreta */
        * { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }

        ::-webkit-scrollbar { width: 10px; height: 10px; }

        ::-webkit-scrollbar-track { background: transparent; }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border: 2px solid transparent;
            border-radius: 999px;
            background-clip: content-box;
        }

        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; background-clip: content-box; }

        /* Transiciones suaves únicamente en propiedades visuales (no en tamaños) */
        a, button, summary, input, select, textarea {
            transition-property: color, background-color, border-color, box-shadow, opacity, transform;
            transition-duration: 160ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }

        button:not(:disabled):active { transform: translateY(1px); }

        /* Tarjetas: elevación sutil al pasar el cursor (solo con mouse) */
        @media (hover: hover) {
            .application-card, .profile-card {
                transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            }

            .application-card:hover, .profile-card:hover {
                transform: translateY(-2px);
                border-color: #cbd5e1;
                box-shadow: 0 12px 28px -18px rgba(15, 23, 42, 0.35);
            }
        }

        /* Filas de tabla más cómodas de seguir */
        tbody tr { transition: background-color 0.15s ease; }

        /* Tarjetas resaltadas cuando algún control interno tiene el foco */
        article:focus-within { border-color: #94a3b8; }

        /* Tipografía más equilibrada en títulos y párrafos */
        h1, h2, .display { text-wrap: balance; }

        p { text-wrap: pretty; }

        /* Sombra progresiva del header al hacer scroll (navegadores compatibles) */
        @supports (animation-timeline: scroll()) {
            body > header {
                animation: yeb-header-shadow linear both;
                animation-timeline: scroll(root);
                animation-range: 0 140px;
            }

            @keyframes yeb-header-shadow {
                to { box-shadow: 0 10px 28px -20px rgba(15, 23, 42, 0.45); }
            }
        }

        /* Elementos plegables con indicador claro */
        summary { cursor: pointer; }

        summary::-webkit-details-marker { color: #94a3b8; }

        /* Pestañas con desplazamiento cómodo y sin barra visible */
        .profile-tabs { scroll-behavior: smooth; scrollbar-width: none; }

        .profile-tabs::-webkit-scrollbar { display: none; }

        /* Entrada suave del contenido principal */
        @keyframes yeb-page-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        main { animation: yeb-page-in 0.25s ease-out both; }

        /* Enlace de salto: visible solo al navegar con teclado */
        .yeb-skip {
            position: fixed;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 60;
            padding: 0.6rem 1rem;
            border-radius: 0.75rem;
            background: #17457c;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            box-shadow: 0 10px 24px -12px rgba(15, 23, 42, 0.6);
            transform: translateY(-180%);
            transition: transform 0.2s ease;
        }

        .yeb-skip:focus,
        .yeb-skip:focus-visible { transform: none; }

        /* Menús horizontales móviles sin barra visible */
        nav[class*="overflow-x-auto"] { scrollbar-width: none; }

        nav[class*="overflow-x-auto"]::-webkit-scrollbar { display: none; }

        /* Botón flotante para volver arriba */
        .yeb-to-top {
            position: fixed;
            right: 1.25rem;
            bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px));
            z-index: 25;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 2.9rem;
            width: 2.9rem;
            border-radius: 999px;
            border: 1px solid rgba(23, 69, 124, 0.2);
            background: #fff;
            color: #17457c;
            box-shadow: 0 12px 30px -14px rgba(15, 23, 42, 0.5);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s, background-color 0.2s, color 0.2s;
        }

        .yeb-to-top:hover { background: #17457c; color: #fff; border-color: #17457c; }

        .yeb-to-top.is-visible { opacity: 1; visibility: visible; transform: none; }

        .yeb-to-top svg { height: 1.15rem; width: 1.15rem; }

        /* Respeta la preferencia del sistema de reducir movimiento */
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }

            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
            }

            main { animation: none; }

            .application-card:hover, .profile-card:hover { transform: none; }
        }

        /* =========================================================================
           Mejoras de calidad de vida globales (shell)
           Solo lo que comparten todas las páginas. El estilo propio de cada
           módulo vive en Modules/<Modulo>/UI/<Modulo>UI.php::styles().
           ========================================================================= */

        /* Enlace de salto: contraste correcto sobre el azul */
        .yeb-skip:focus,
        .yeb-skip:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }

        /* Tokens de animación compartidos */
        @keyframes yeb-rise {
            from { transform: translateY(8px); }
            to { transform: none; }
        }

        @keyframes yeb-row-in {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: none; }
        }

        /* Vistas que devuelven un bloque único (perfiles públicos, detalle de
           vacante, paneles de administración y páginas informativas) */
        main > article,
        main > section,
        main > div {
            animation: yeb-rise 0.3s cubic-bezier(0.22, 0.61, 0.36, 1) backwards;
        }

        /* Tarjetas de panel: elevación al pasar el cursor */
        .rounded-2xl.border.bg-white.shadow-sm,
        .rounded-3xl.border.bg-white.shadow-sm {
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }

        @media (hover: hover) {
            .rounded-2xl.border.bg-white.shadow-sm:hover,
            .rounded-3xl.border.bg-white.shadow-sm:hover {
                border-color: #cbd5e1;
                box-shadow: 0 18px 36px -28px rgba(15, 23, 42, 0.5);
            }
        }

        /* Filas de tablas resaltadas cuando un control tiene foco */
        tbody tr:focus-within { background-color: #f1f5f9; }

        /* Campos de todos los módulos: mismo color de foco */
        input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):focus,
        select:focus,
        textarea:focus {
            border-color: #17457c;
        }

        /* Progreso de lectura de la página (navegadores compatibles) */
        @supports (animation-timeline: scroll()) {
            @media (prefers-reduced-motion: no-preference) {
                body::before {
                    content: "";
                    position: fixed;
                    inset: 0 0 auto 0;
                    height: 2px;
                    z-index: 60;
                    pointer-events: none;
                    transform-origin: left center;
                    background: linear-gradient(90deg, #17457c, #3b82f6);
                    animation: yeb-read-progress linear both;
                    animation-timeline: scroll(root);
                }

                @keyframes yeb-read-progress {
                    from { transform: scaleX(0); }
                    to { transform: scaleX(1); }
                }
            }
        }

    </style>

    ' . self::moduleStylesHtml() . '

</head>

<body class="bg-[#f8fafc] text-slate-800 antialiased flex flex-col min-h-screen">

    <a href="#contenido" class="yeb-skip">Saltar al contenido</a>

    ' . $headerHtml . '

    <main id="contenido" class="mx-auto max-w-6xl px-5 md:px-8 flex-1 w-full py-4">

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

    <div id="yeb-push-toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none px-4 sm:px-0"></div>

    <script src="' . self::assetUrl('push-notifications.js') . '"></script>

    <button type="button" id="yeb-to-top" class="yeb-to-top" aria-label="Volver arriba" title="Volver arriba">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path></svg>
    </button>

    <script>
        (function () {
            try {
                var boton = document.getElementById("yeb-to-top");
                if (!boton) return;

                var actualizar = function () {
                    if (window.scrollY > 320) boton.classList.add("is-visible");
                    else boton.classList.remove("is-visible");
                };

                window.addEventListener("scroll", actualizar, { passive: true });
                actualizar();

                boton.addEventListener("click", function () {
                    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
                    window.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" });
                });
            } catch (error) {
                /* Si el navegador no lo soporta, la pagina sigue funcionando igual. */
            }
        })();
    </script>

</body>

</html>';

    }


    /**

     * Pantalla mostrada cuando el usuario no tiene permiso para ver un perfil.

     */

    public static function accessDenied(?string $error, ?array $user): string
    {

        $detail = $error ?: 'No tienes permisos para consultar esa informacion.';



        return self::alerts(null, $detail) . '

        <div class="max-w-2xl mx-auto pb-10">

            <article class="rounded-3xl border border-slate-200/90 bg-white p-8 text-center shadow-sm">

                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-rose-500 mb-4">' . self::iconLock('w-7 h-7') . '</div>

                <h1 class="text-xl font-bold text-slate-900">Acceso restringido</h1>

                <p class="mt-2 text-sm text-slate-500">Esta informacion solo se comparte entre la empresa y los candidatos que tienen una postulacion activa.</p>

                <a href="?action=dashboard" class="primary-button mt-6 inline-flex items-center gap-2">' . self::iconArrowLeft('w-4 h-4') . ' Volver al inicio</a>

            </article>

        </div>';

    }


    public static function home(array $jobs, ?array $user, string $action, ?string $message, ?string $error, ?IdScanConfig $idScan = null): string
    {

        $alerts = self::alerts($message, $error);

        $idScan ??= IdScanConfig::fromArray([]);



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

                        <!-- Aviso Legal de Términos y Privacidad -->

                        <div class="mt-5 p-3.5 rounded-2xl bg-slate-50 border border-slate-200/90 text-center">

                            <p class="text-xs text-slate-500 leading-relaxed">

                                Al crear o haber creado una cuenta, aceptas nuestros nuestros <a href="?action=legal" target="_blank" class="font-bold text-[#17457c] underline hover:text-[#0f2d52]">[Términos de uso]</a>, politica de cookies y terminos y condicione.

                            </p>

                            <a href="?action=legal" target="_blank" class="mt-2.5 inline-flex items-center justify-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-[#17457c] hover:bg-blue-50/50 hover:border-blue-200 shadow-sm transition">

                                ' . self::iconShield('w-3.5 h-3.5 text-[#17457c]') . ' <span>Ver Políticas Legales y Términos</span>

                            </a>

                        </div>



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

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-center text-xs">

                                <button type="button" onclick="fillLogin(\'ana@yeb.test\', \'password\')" class="rounded-lg bg-white border border-blue-200 p-2 hover:bg-blue-100/50 transition">

                                    <strong class="block text-slate-800">Candidata</strong>

                                    <span class="text-[10px] text-slate-500">ana@yeb</span>

                                </button>

                                <button type="button" onclick="fillLogin(\'menor@yeb.test\', \'password\')" class="rounded-lg bg-amber-50 border border-amber-300 p-2 hover:bg-amber-100 transition shadow-sm">

                                    <strong class="block text-amber-900">Menor de Edad</strong>

                                    <span class="text-[10px] text-amber-700">menor@yeb</span>

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

            $scripts = '';



            $tabs = '

            <div class="mb-6 flex rounded-2xl bg-slate-100 p-1.5 border border-slate-200/80">

                <a href="?action=register" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl py-2.5 text-center text-xs font-bold transition ' . (!$isCompany ? 'bg-[#17457c] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900') . '">

                    ' . self::iconUser('w-4 h-4') . ' Soy Candidato

                </a>

                <a href="?action=register_company" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl py-2.5 text-center text-xs font-bold transition ' . ($isCompany ? 'bg-[#17457c] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900') . '">

                    ' . self::iconBuilding('w-4 h-4') . ' Soy Empresa

                </a>

            </div>';



            if (!$isCompany) {

                // Candidato

                $formContent = '

                <div class="text-center mb-6">

                    <h1 class="display text-3xl font-extrabold text-slate-900">Crear Perfil de Candidato</h1>

                    <p class="mt-2 text-sm text-slate-500">Regístrate en menos de dos minutos y empieza a postularte a vacantes verificadas.</p>

                </div>

                ' . $tabs . '

                <form method="post" action="?action=register" enctype="multipart/form-data" class="space-y-4" id="idScanForm">

                    <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

                    <input type="hidden" name="id_scan" id="idScan" value="' . self::e($old['id_scan'] ?? '') . '">

                    <div>

                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nombre Completo</label>

                        <div class="relative">

                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconUser('w-5 h-5') . '</span>

                            <input required name="name" id="idScanName" value="' . self::e($old['name'] ?? '') . '" placeholder="Ej: Ana María Gómez" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

                        </div>

                        <p class="text-[11px] text-slate-400 mt-1">Se completa con el nombre del documento que escaneaste; si el lector se equivocó puedes corregirlo.</p>

                    </div>

                    ' . IdentityUI::idScanSection($idScan) . '

                    <div>

                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Número del Documento</label>

                        <div class="relative">

                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconShield('w-5 h-5') . '</span>

                            <input required readonly name="cedula" id="idScanDocument" value="' . self::e($old['cedula'] ?? '') . '" inputmode="numeric" placeholder="Se completa con la lectura del documento" class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

                        </div>

                        <p class="text-[11px] text-slate-400 mt-1">Cédula de ciudadanía o tarjeta de identidad (TI). Se completa con la lectura del reverso.</p>

                    </div>

                    <div>

                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Fecha de nacimiento</label>

                        <div class="relative">

                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconCalendar('w-5 h-5') . '</span>

                            <input required readonly name="birth_date" id="idScanBirthDate" type="date" max="' . self::e(date('Y-m-d')) . '" value="' . self::e($old['birth_date'] ?? '') . '" class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

                        </div>

                        <p class="text-[11px] text-slate-400 mt-1">Se toma del documento que adjuntaste, no se puede editar.</p>

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

                    <div>

                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Confirmar Contraseña</label>

                        <div class="relative">

                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconLock('w-5 h-5') . '</span>

                            <input required name="password_confirm" type="password" placeholder="Repite tu contraseña" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

                        </div>

                    </div>

                    <div class="rounded-xl bg-blue-50 border border-blue-200 p-3 text-xs text-blue-900 flex items-start gap-2">

                        ' . self::iconShield('w-4 h-4 text-blue-700 flex-shrink-0 mt-0.5') . '

                        <span>Leemos el código de barras de tu cédula o tarjeta de identidad para validar el documento y confirmar que tienes ' . self::e($idScan->ageRangeLabel()) . '.</span>

                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600 flex items-start gap-2">

                        ' . self::iconCheck('w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5') . '

                        <span>Acceso 100% gratuito, postulaciones directas y alertas de empleo.</span>

                    </div>

                    <button type="submit" id="idScanSubmit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] p-3.5 font-bold text-white shadow-md shadow-blue-900/10 transition hover:bg-[#12365f] text-sm">

                        <span>Crear Cuenta de Candidato</span> ' . self::iconArrowRight('w-4 h-4') . '

                    </button>

                </form>';

                $scripts = IdentityUI::idScanScript();

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

                    <div>

                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Confirmar Contraseña</label>

                        <div class="relative">

                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">' . self::iconLock('w-5 h-5') . '</span>

                            <input required name="password_confirm" type="password" placeholder="Repite tu contraseña" class="w-full rounded-xl border border-slate-200 pl-11 pr-4 py-3 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

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

                        <span>La cédula del reclutador se registra solo como dato de la empresa y no puede repetirse en otra cuenta de empresa.</span>

                    </div>

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



                        <!-- Aviso Legal de Términos y Privacidad -->

                        <div class="mt-5 p-3.5 rounded-2xl bg-slate-50 border border-slate-200/90 text-center">

                            <p class="text-xs text-slate-500 leading-relaxed">

                                Al crear o haber creado una cuenta, aceptas nuestros nuestros <a href="?action=legal" target="_blank" class="font-bold text-[#17457c] underline hover:text-[#0f2d52]">[Términos de uso]</a>, politica de cookies y terminos y condicione.

                            </p>

                            <a href="?action=legal" target="_blank" class="mt-2.5 inline-flex items-center justify-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-[#17457c] hover:bg-blue-50/50 hover:border-blue-200 shadow-sm transition">

                                ' . self::iconShield('w-3.5 h-3.5 text-[#17457c]') . ' <span>Ver Políticas Legales y Términos</span>

                            </a>

                        </div>

                        <div class="mt-6 pt-5 border-t border-slate-100 text-center">

                            <p class="text-sm text-slate-500">

                                ¿Ya tienes una cuenta registrada?

                                <a href="?action=login" class="font-bold text-[#17457c] hover:underline">Ingresar</a>

                            </p>

                        </div>

                    </section>

                </div>

            </div>' . self::passwordMatchScript() . $scripts;

        }



        // LANDING PAGE VIEW (Default)
        $q = trim((string) ($_GET['q'] ?? $_GET['title'] ?? ''));
        $location = trim((string) ($_GET['location'] ?? $_GET['city'] ?? ''));
        $hasSearch = ($q !== '' || $location !== '');

        // Función auxiliar interna para normalizar acentos y mayúsculas
        $normalize = static function (string $str): string {
            $cleaned = mb_strtolower($str, 'UTF-8');
            return strtr($cleaned, [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ñ' => 'n',
                'Á' => 'a',
                'É' => 'e',
                'Í' => 'i',
                'Ó' => 'o',
                'Ú' => 'u',
                'Ü' => 'u',
                'Ñ' => 'n'
            ]);
        };

        // Filtrar y ordenar por coincidencia con lo puesto en el buscador de la landing
        $matchedJobs = $jobs;
        if ($hasSearch) {
            $qNorm = $normalize($q);
            $locNorm = $normalize($location);

            $scored = [];
            foreach ($jobs as $job) {
                $score = 0;
                $tNorm = $normalize($job['title'] ?? '');
                $dNorm = $normalize($job['description'] ?? '');
                $cNorm = $normalize($job['company_name'] ?? '');
                $cityNorm = $normalize($job['city'] ?? '');
                $wmNorm = $normalize($job['work_mode'] ?? '');

                if ($qNorm !== '') {
                    if (str_contains($tNorm, $qNorm))
                        $score += 4;
                    if (str_contains($cNorm, $qNorm))
                        $score += 3;
                    if (str_contains($dNorm, $qNorm))
                        $score += 1;
                }
                if ($locNorm !== '') {
                    if (str_contains($cityNorm, $locNorm))
                        $score += 3;
                    if (str_contains($wmNorm, $locNorm))
                        $score += 3;
                }

                if ($score > 0) {
                    $job['landing_match_score'] = $score;
                    $scored[] = $job;
                }
            }

            usort($scored, function ($a, $b) {
                return ($b['landing_match_score'] ?? 0) <=> ($a['landing_match_score'] ?? 0);
            });
            $matchedJobs = $scored;
        }

        // Mostrar únicamente las 8 vacantes destacadas que mejor coincidan con el buscador
        $featuredJobs = array_slice($matchedJobs, 0, 8);

        $cards = '';
        foreach ($matchedJobs as $jobIndex => $job) {

            $hiddenCardClass = $jobIndex >= 8 ? ' hidden' : '';

            $modePill = match (strtolower($job['work_mode'])) {

                'remote', 'remoto' => '<span class="rounded-full bg-purple-50 text-purple-700 px-3 py-1 text-xs font-bold border border-purple-200">Remoto</span>',

                'hybrid', 'híbrido' => '<span class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-bold border border-blue-200">Híbrido</span>',

                default => '<span class="rounded-full bg-teal-50 text-teal-700 px-3 py-1 text-xs font-bold border border-teal-200">Presencial</span>'

            };



            $salaryDisplay = ($job['salary_min'] || $job['salary_max'])

                ? '$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.')

                : 'Salario a convenir';



            $cards .= '

            <article class="landing-job-card min-w-0 rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg flex flex-col justify-between' . $hiddenCardClass . '"

                data-title="' . self::e((string) ($job['title'] ?? '')) . '"

                data-company="' . self::e((string) ($job['company_name'] ?? '')) . '"

                data-city="' . self::e((string) ($job['city'] ?? '')) . '"

                data-work-mode="' . self::e((string) ($job['work_mode'] ?? '')) . '"

                data-search="' . self::e(($job['title'] ?? '') . ' ' . ($job['company_name'] ?? '') . ' ' . ($job['description'] ?? '')) . '"

                data-location="' . self::e(($job['city'] ?? '') . ' ' . ($job['work_mode'] ?? '')) . '">

                <div class="min-w-0">

                    <div class="flex items-center justify-between gap-3 mb-4">

                        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 text-[#17457c] font-extrabold text-base border border-blue-100">

                            ' . strtoupper(substr($job['company_name'], 0, 1)) . '

                        </div>

                        ' . $modePill . '

                    </div>

                    <h3 class="break-words text-lg font-bold text-slate-900 hover:text-[#17457c] transition">

                        <a href="?action=job_detail&id=' . $job['id'] . '">' . self::e($job['title']) . '</a>

                    </h3>

                    <p class="mt-1 text-xs font-semibold text-slate-500 flex items-center gap-1.5">

                        <span class="min-w-0 break-words text-slate-800">' . self::e($job['company_name']) . '</span>

                        <span class="flex-shrink-0">·</span>

                        <span class="min-w-0 break-words">' . self::iconLocation('w-3.5 h-3.5 inline text-slate-400') . ' ' . self::e($job['city']) . '</span>

                    </p>

                    <p class="mt-3.5 break-words text-xs leading-5 text-slate-600 line-clamp-3">' . self::e($job['description']) . '</p>

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

                                <input type="hidden" name="return_to" value="?action=home">

                                <button class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] hover:underline bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-lg transition hover:bg-blue-100">Postularme ' . self::iconArrowRight('w-3 h-3') . '</button>

                            </form>'

                : '<a href="?action=job_detail&id=' . $job['id'] . '" class="inline-flex items-center gap-1 text-xs font-bold text-[#17457c] hover:underline bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg transition hover:bg-slate-100">Ver detalle ' . self::iconArrowRight('w-3 h-3') . '</a>') . '

                    </div>

                </div>

            </article>';

        }



        if (!$cards) {
            $cards = '<div class="col-span-full py-16 text-center text-slate-500 text-sm">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">' . self::iconSearch('w-6 h-6') . '</div>
                <p class="font-bold text-slate-700 text-base">No se encontraron vacantes con los terminos de busqueda</p>
                <p class="text-xs text-slate-400 mt-1">Prueba con palabras clave como Barista, Panadero, Desarrollador, Ventas o cambia la ciudad.</p>
                <a href="./#vacantes" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-[#17457c] px-4 py-2 text-xs font-bold text-white hover:bg-[#12365f] transition">Ver todas las vacantes disponibles</a>
            </div>';
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
                <form id="landingSearchForm" class="relative z-10 mt-10 grid gap-3 rounded-2xl bg-white p-3.5 shadow-2xl text-left md:grid-cols-[1.2fr_1fr_auto] max-w-4xl mx-auto" method="get" action="./#vacantes">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-1">
                        <span class="text-slate-400">' . self::iconSearch('w-5 h-5') . '</span>
                        <input id="landingSearchQuery" name="q" value="' . self::e($q) . '" placeholder="Cargo, palabra clave o empresa (ej: Barista, Panadero, Desarrollador)" class="w-full py-2.5 text-slate-900 outline-none text-sm placeholder-slate-400">
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-1">
                        <span class="text-slate-400">' . self::iconLocation('w-5 h-5') . '</span>
                        <input id="landingSearchLocation" name="location" value="' . self::e($location) . '" placeholder="Ciudad o modalidad (ej: Medellin, Bogota, Remoto)" class="w-full py-2.5 text-slate-900 outline-none text-sm placeholder-slate-400">
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#17457c] px-7 py-3 font-bold text-white transition hover:bg-[#12365f] text-sm shadow-md">
                            ' . self::iconSearch('w-4 h-4') . ' Buscar Empleos
                        </button>
                        <button type="button" id="landingClearSearch" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs font-bold text-slate-600 hover:bg-slate-100 transition' . ($hasSearch ? '' : ' hidden') . '" title="Limpiar busqueda">✕</button>
                    </div>
                </form>

<!-- QUICK TAGS -->
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs text-slate-300">
                    <span class="text-slate-400 font-semibold">Busquedas populares:</span>
                    <a href="./?location=Remote#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Remoto</a>
                    <a href="./?location=Medellin#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Medellin</a>
                    <a href="./?location=Bogota#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Bogota</a>
                    <a href="./?q=Barista#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Barista</a>
                    <a href="./?q=Panadero#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Panadero</a>
                    <a href="./?q=Ventas#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Ventas</a>
                    <a href="./?q=Desarrollador#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Desarrollador</a>
                    <a href="./?q=Junior#vacantes" class="rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-white transition">Junior</a>
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
                    ' . ($hasSearch ? '<p class="mt-1 text-sm text-slate-500">Mostrando las 8 mejores coincidencias para tu busqueda.</p>' : '<p class="mt-1 text-sm text-slate-500">Oportunidades laborales para jovenes con o sin experiencia previa.</p>') . '
                </div>
                <div class="flex items-center gap-2">
                    <span id="landingJobCount" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-3.5 py-1 text-xs font-bold text-slate-700">
                        ' . count($featuredJobs) . ' de ' . count($matchedJobs) . ' vacantes ' . ($hasSearch ? 'encontradas' : 'activas') . '
                    </span>
                    ' . ($hasSearch ? '<a href="./#vacantes" class="text-xs font-bold text-[#17457c] hover:underline bg-blue-50 border border-blue-200 px-3 py-1 rounded-full transition">Limpiar filtro</a>' : '') . '
                </div>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">' . $cards . '</div>
            <div id="landingJobsEmpty" class="col-span-full py-16 text-center text-slate-500 text-sm" style="display:none;">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">' . self::iconSearch('w-6 h-6') . '</div>
                <p class="font-bold text-slate-700 text-base">No se encontraron vacantes con los terminos de busqueda</p>
                <p class="text-xs text-slate-400 mt-1">Prueba con palabras clave como Barista, Panadero, Desarrollador, Ventas o cambia la ciudad.</p>
            </div>
            ' . (count($matchedJobs) > 8 ? '
            <div id="landingViewAllWrap" class="mt-10 text-center">
                <a id="landingViewAll" href="?action=search' . ($hasSearch ? '&q=' . rawurlencode($q) . '&location=' . rawurlencode($location) : '') . '" class="inline-flex items-center gap-2 rounded-2xl bg-[#17457c] px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-900/10 transition hover:bg-[#12365f] hover:shadow-xl">
                    <span>Ver todas las ' . count($matchedJobs) . ' vacantes en el buscador avanzado</span> ' . self::iconArrowRight('w-4 h-4') . '
                </a>
            </div>' : '') . '
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

        </section>

        <script>

        (function () {

            var form = document.getElementById("landingSearchForm");

            if (!form) {

                return;

            }

            var queryInput = document.getElementById("landingSearchQuery");

            var locationInput = document.getElementById("landingSearchLocation");

            var cards = Array.prototype.slice.call(document.querySelectorAll(".landing-job-card"));

            var countLabel = document.getElementById("landingJobCount");

            var emptyState = document.getElementById("landingJobsEmpty");

            var clearButton = document.getElementById("landingClearSearch");

            var viewAllLink = document.getElementById("landingViewAll");

            var viewAllWrap = document.getElementById("landingViewAllWrap");

            var maxVisible = 8;

            function normalizeText(value) {

                return String(value || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

            }

            function filterLandingJobs() {

                var query = normalizeText(queryInput ? queryInput.value.trim() : "");

                var location = normalizeText(locationInput ? locationInput.value.trim() : "");

                var matched = 0;

                var shown = 0;

                cards.forEach(function (card) {

                    var searchable = normalizeText(card.getAttribute("data-search"));

                    var locationText = normalizeText(card.getAttribute("data-location"));

                    var matches = (query === "" || searchable.indexOf(query) !== -1)

                        && (location === "" || locationText.indexOf(location) !== -1);

                    if (matches) {

                        matched++;

                        if (shown < maxVisible) {

                            card.classList.remove("hidden");

                            shown++;

                        } else {

                            card.classList.add("hidden");

                        }

                    } else {

                        card.classList.add("hidden");

                    }

                });

                if (countLabel) {

                    countLabel.textContent = shown + " de " + matched + " vacantes " + ((query !== "" || location !== "") ? "encontradas" : "activas");

                }

                if (emptyState) {

                    emptyState.style.display = (matched === 0 && cards.length > 0) ? "" : "none";

                }

                if (clearButton) {

                    clearButton.classList.toggle("hidden", query === "" && location === "");

                }

                if (viewAllLink && viewAllWrap) {

                    if (matched > maxVisible) {

                        viewAllWrap.style.display = "";

                        var label = viewAllLink.querySelector("span");

                        if (label) {

                            label.textContent = "Ver todas las " + matched + " vacantes en el buscador avanzado";

                        }

                        var params = new URLSearchParams();

                        if (queryInput && queryInput.value.trim() !== "") {

                            params.set("q", queryInput.value.trim());

                        }

                        if (locationInput && locationInput.value.trim() !== "") {

                            params.set("location", locationInput.value.trim());

                        }

                        var suffix = params.toString();

                        viewAllLink.setAttribute("href", "?action=search" + (suffix !== "" ? "&" + suffix : ""));

                    } else {

                        viewAllWrap.style.display = "none";

                    }

                }

            }

            function syncLandingUrl() {

                var params = new URLSearchParams(window.location.search);

                if (queryInput && queryInput.value.trim() !== "") {

                    params.set("q", queryInput.value.trim());

                } else {

                    params.delete("q");

                }

                if (locationInput && locationInput.value.trim() !== "") {

                    params.set("location", locationInput.value.trim());

                } else {

                    params.delete("location");

                }

                var search = params.toString();

                if (window.history && window.history.replaceState) {

                    window.history.replaceState(null, "", window.location.pathname + (search !== "" ? "?" + search : "") + "#vacantes");

                }

            }

            [queryInput, locationInput].forEach(function (input) {

                if (!input) {

                    return;

                }

                input.addEventListener("input", filterLandingJobs);

            });

            form.addEventListener("submit", function (event) {

                event.preventDefault();

                filterLandingJobs();

                syncLandingUrl();

            });

            if (clearButton) {

                clearButton.addEventListener("click", function () {

                    if (queryInput) {

                        queryInput.value = "";

                    }

                    if (locationInput) {

                        locationInput.value = "";

                    }

                    filterLandingJobs();

                    syncLandingUrl();

                    if (queryInput) {

                        queryInput.focus();

                    }

                });

            }

            document.querySelectorAll("a[href^=\"./?q=\"], a[href^=\"./?location=\"]").forEach(function (link) {

                link.addEventListener("click", function (event) {

                    event.preventDefault();

                    var target = new URL(link.href, window.location.href);

                    if (queryInput) {

                        queryInput.value = target.searchParams.get("q") || "";

                    }

                    if (locationInput) {

                        locationInput.value = target.searchParams.get("location") || "";

                    }

                    filterLandingJobs();

                    syncLandingUrl();

                    var section = document.getElementById("vacantes");

                    if (section) {

                        section.scrollIntoView({ behavior: "smooth", block: "start" });

                    }

                });

            });

            filterLandingJobs();

        })();

        </script>';

    }


    public static function legalPage(): string
    {

        return '

        <div class="min-h-[calc(100vh-220px)] py-10 px-4 md:px-8 bg-slate-50/50">

            <div class="max-w-5xl mx-auto">

                <!-- Migas / Volver -->

                <div class="mb-6 flex items-center justify-between">

                    <a href="./" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-[#17457c] transition">

                        ' . self::iconArrowLeft('w-4 h-4') . ' Volver al Portal

                    </a>

                    <div class="flex items-center gap-2">

                        <a href="?action=register" class="inline-flex items-center gap-1.5 rounded-xl bg-[#17457c] text-white text-xs font-bold px-4 py-2 hover:bg-[#12365f] shadow-sm transition">

                            Crear Cuenta

                        </a>

                        <a href="?action=login" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-xs font-bold px-4 py-2 hover:bg-slate-100 shadow-sm transition">

                            Iniciar Sesión

                        </a>

                    </div>

                </div>



                <!-- Cabecera Oficial -->

                <div class="rounded-3xl border border-slate-200/90 bg-white p-8 md:p-12 shadow-sm mb-8 text-center relative overflow-hidden">

                    <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-[#17457c] border border-blue-200 mb-4">

                        ' . self::iconShield('w-4 h-4 text-[#17457c]') . ' Marco Legal Institucional

                    </div>

                    <h1 class="display text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 leading-tight mb-4">

                        POLÍTICAS LEGALES, TRATAMIENTO DE DATOS PERSONALES Y TÉRMINOS DE USO

                    </h1>

                    <p class="text-base sm:text-lg font-bold text-[#17457c] tracking-wide mb-2">

                        PLATAFORMA YOUTH EMPLOYMENT BRIDGE (YEB)

                    </p>

                    <p class="text-xs sm:text-sm text-slate-500 max-w-2xl mx-auto">

                        Centro de Tecnología de la Manufactura Avanzada (CTMA) &middot; Servicio Nacional de Aprendizaje (SENA) &middot; Ficha 3223875 &middot; ADSO

                    </p>

                    <div class="mt-6 flex flex-wrap justify-center gap-2 text-[11px] font-semibold text-slate-600">

                        <span class="rounded-lg bg-slate-100 border border-slate-200 px-3 py-1">Ley Estatutaria 1581 de 2012</span>

                        <span class="rounded-lg bg-slate-100 border border-slate-200 px-3 py-1">Decreto 1377 de 2013</span>

                        <span class="rounded-lg bg-slate-100 border border-slate-200 px-3 py-1">Ley 1098 de 2006</span>

                        <span class="rounded-lg bg-slate-100 border border-slate-200 px-3 py-1">Ley 1480 de 2011</span>

                        <span class="rounded-lg bg-slate-100 border border-slate-200 px-3 py-1">Ley 2300 de 2023</span>

                    </div>

                </div>



                <!-- Navegación de Secciones Rápidas -->

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-8">

                    <a href="#seccion-1" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#17457c] hover:shadow-md transition group">

                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#17457c] block mb-1">Sección I</span>

                        <strong class="text-sm text-slate-800 group-hover:text-[#17457c] block leading-snug">Tratamiento de Datos Personales</strong>

                        <span class="text-xs text-slate-400 mt-1 block">Ley 1581 / Dec. 1377 / PQRS</span>

                    </a>

                    <a href="#seccion-2" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#17457c] hover:shadow-md transition group">

                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#17457c] block mb-1">Sección II</span>

                        <strong class="text-sm text-slate-800 group-hover:text-[#17457c] block leading-snug">Política de Cookies</strong>

                        <span class="text-xs text-slate-400 mt-1 block">Cookies técnicas, CSRF y control</span>

                    </a>

                    <a href="#seccion-3" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-[#17457c] hover:shadow-md transition group">

                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#17457c] block mb-1">Sección III</span>

                        <strong class="text-sm text-slate-800 group-hover:text-[#17457c] block leading-snug">Términos y Condiciones</strong>

                        <span class="text-xs text-slate-400 mt-1 block">Gratuidad, roles y Ley 2300</span>

                    </a>

                </div>



                <div class="space-y-8">

                    <!-- ==============================================

                         SECCIÓN 1: POLÍTICA DE TRATAMIENTO DE DATOS

                         ============================================== -->

                    <section id="seccion-1" class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-10 shadow-sm">

                        <div class="flex items-center gap-3 border-b border-slate-100 pb-5 mb-6">

                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white font-extrabold text-sm shadow-sm">

                                1

                            </span>

                            <div>

                                <span class="text-[10px] font-bold uppercase tracking-wider text-[#17457c]">Normativa de Privacidad y Hábeas Data</span>

                                <h2 class="display text-2xl md:text-3xl font-extrabold text-slate-900">

                                    1. SECCIÓN I: POLÍTICA DE TRATAMIENTO DE DATOS PERSONALES

                                </h2>

                                <p class="text-xs text-slate-500 font-semibold">(LEY 1581 DE 2012 Y DECRETO 1377 DE 2013)</p>

                            </div>

                        </div>



                        <!-- 1.1 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">1.1</span>

                                Identificación del Responsable del Tratamiento y Marco Normativo

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-4">

                                La presente Política de Tratamiento de Datos Personales rige de manera obligatoria las operaciones de recolección, almacenamiento, uso, circulación, transmisión, transferencia y supresión de datos personales ejecutadas a través de la plataforma web Youth Employment Bridge (YEB).

                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Responsables del Tratamiento</h4>

                                    <p class="text-sm font-semibold text-slate-800">María Paula Herrera Rivas</p>

                                    <p class="text-sm font-semibold text-slate-800">Juan Manuel Cardona Molina</p>

                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Contexto Institucional y Académico</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Proyecto tecnológico desarrollado en el Centro de Tecnología de la Manufactura Avanzada (CTMA) del Servicio Nacional de Aprendizaje (SENA), Ficha 3223875 del programa de formación Tecnología en Análisis y Desarrollo de Software (ADSO).

                                    </p>

                                </div>

                            </div>

                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 mb-4">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-[#17457c] mb-1">Área y Oficial Encargado de la Atención de PQRS</h4>

                                <p class="text-xs text-slate-700 leading-relaxed">

                                    En cumplimiento del artículo 13, parágrafo 3 del Decreto Reglamentario 1377 de 2013, la función de atención de peticiones, consultas y reclamos de Hábeas Data es asumida formalmente por la <strong>Oficina Interna de Protección de Datos de YEB</strong>, canalizada a través del correo electrónico institucional de soporte técnico: <a href="mailto:soporte-yeb@sena.edu.co" class="font-bold underline text-[#17457c]">soporte-yeb@sena.edu.co</a> y del canal de atención escrita del proyecto CTMA-SENA.

                                </p>

                            </div>

                            <div>

                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Marco Normativo Aplicable</h4>

                                <ul class="space-y-1.5 text-xs text-slate-600 pl-4 list-disc">

                                    <li><strong>Artículo 15 de la Constitución Política de Colombia:</strong> Garantía fundamental del derecho al buen nombre y al Hábeas Data.</li>

                                    <li><strong>Ley Estatutaria 1581 de 2012:</strong> Por la cual se dictan disposiciones generales para la protección de datos personales.</li>

                                    <li><strong>Decreto Reglamentario 1377 de 2013:</strong> Por el cual se reglamentan los aspectos procedimentales, de consentimiento y de políticas de tratamiento de la Ley 1581 de 2012.</li>

                                    <li><strong>Ley 1098 de 2006 (Código de la Infancia y la Adolescencia):</strong> En lo atinente a la protección prevalente y reforzada de los derechos fundamentales de los niños, niñas y adolescentes (población objetivo de 16 y 17 años).</li>

                                </ul>

                            </div>

                        </div>



                        <!-- 1.2 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">1.2</span>

                                Clasificación y Catálogo de Datos Personales Recolectados

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-4">

                                En virtud de los principios de libertad, veracidad, transparencia, seguridad y acceso restringido, los datos recolectados en la base de datos relacional <code class="font-mono text-xs bg-slate-100 px-1.5 py-0.5 rounded text-[#17457c]">yeb_portal</code> se estructuran según su nivel de sensibilidad y su vínculo directo con la arquitectura del sistema:

                            </p>

                            <div class="overflow-x-auto rounded-2xl border border-slate-200">

                                <table class="w-full text-left text-xs text-slate-600">

                                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">

                                        <tr>

                                            <th class="p-3.5">Categoría de Dato</th>

                                            <th class="p-3.5">Campos Específicos Capturados (yeb_portal)</th>

                                            <th class="p-3.5">Nivel de Sensibilidad y Naturaleza Jurídica</th>

                                        </tr>

                                    </thead>

                                    <tbody class="divide-y divide-slate-100">

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Datos de Acceso y Perfil del Candidato</td>

                                            <td class="p-3.5"><strong>users:</strong> Nombre completo (name), correo electrónico (email), contraseña cifrada (password), teléfono (phone), ubicación/barrio/ciudad (location), titular profesional (professional_title), biografía (bio), habilidades (skills), experiencia (experience), educación (education), disponibilidad (availability), enlaces externos (linkedin_url, portfolio_url), ruta y formato de foto de perfil (profile_photo_path, profile_photo_mime). Requisito técnico pendiente: Incorporación del campo fecha de nacimiento (users.birthdate DATE) para cálculo automatizado de minoría de edad [HU-021].</td>

                                            <td class="p-3.5"><span class="inline-block rounded-lg bg-blue-50 border border-blue-200 text-[#17457c] font-semibold px-2 py-1">Dato Personal / Privado (Sujeto a autorización expresa de tratamiento).</span></td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Archivos Adjuntos (Hoja de Vida)</td>

                                            <td class="p-3.5"><strong>users:</strong> Ruta de almacenamiento en directorio privado (cv_path), nombre original del archivo (cv_original_name), tipo MIME (cv_mime), tamaño en bytes (cv_size) y marca de tiempo (cv_uploaded_at). Formato estándar recomendado: PDF (hasta 5 MB). Aviso de inconsistencia técnica: Aunque el sistema soporta temporalmente formatos DOC y DOCX [ADR007], en atención a la regla [RN-006] el estándar legal óptimo de integridad es PDF.</td>

                                            <td class="p-3.5"><span class="inline-block rounded-lg bg-amber-50 border border-amber-200 text-amber-800 font-semibold px-2 py-1">Dato Privado / Sensible por Vinculación Curricular (Acceso restringido estricto).</span></td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Datos Corporativos y de Contacto Empresarial</td>

                                            <td class="p-3.5"><strong>companies:</strong> Razón social/nombre comercial (name), descripción corporativa (description), ciudad sede (city), sector/industria (industry), sitio web (website), teléfono de contacto (phone), correo de contacto (contact_email), tamaño empresarial (size), ruta y tipo de logo (profile_photo_path, profile_photo_mime). Requisito legal/técnico pendiente: Creación del campo NIT (companies.nit VARCHAR(20)) exigido por la regla [RN-003] para validación tributaria de la empresa.</td>

                                            <td class="p-3.5"><span class="inline-block rounded-lg bg-slate-100 border border-slate-200 text-slate-700 font-semibold px-2 py-1">Dato Comercial y de Contacto Privado</span></td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Ofertas Laborales y Vacantes</td>

                                            <td class="p-3.5"><strong>jobs:</strong> Título del cargo (title), descripción (description), ciudad (city), modalidad (work_mode), experiencia requerida (experience), rango salarial (salary_min, salary_max) o la condición "a convenir" [CR-001], estado (status: draft, published, closed) y fechas de registro/actualización.</td>

                                            <td class="p-3.5"><span class="inline-block rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold px-2 py-1">Dato Público / Operativo del Servicio</span></td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Postulaciones e Historial Laboral</td>

                                            <td class="p-3.5"><strong>applications:</strong> Vínculo vacante-candidato (job_id, user_id), estado del proceso (status: pending, review, interview, accepted, rejected) y fecha de postulación (created_at).</td>

                                            <td class="p-3.5"><span class="inline-block rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800 font-semibold px-2 py-1">Dato Privado / Transaccional</span></td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Preferencias e Interacciones Internas</td>

                                            <td class="p-3.5"><strong>saved_jobs y hidden_jobs:</strong> Claves compuestas (user_id, job_id) y fecha de registro para marcado de vacantes favoritas u ocultas. <strong>notifications:</strong> Mensaje interno (message), estado de lectura (read_at) y fecha.</td>

                                            <td class="p-3.5"><span class="inline-block rounded-lg bg-slate-100 border border-slate-200 text-slate-700 font-semibold px-2 py-1">Dato Interno / Transaccional</span></td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Datos Técnicos y de Auditoría del Sistema</td>

                                            <td class="p-3.5"><strong>audit_logs:</strong> Dirección IP (ip_address), cliente/navegador (user_agent), ruta consultada (route), módulo (module), acción (action), estado del resultado (status), descripción legible (description), payload en formato JSON (payload) y fecha/hora (created_at).</td>

                                            <td class="p-3.5"><span class="inline-block rounded-lg bg-rose-50 border border-rose-200 text-rose-800 font-semibold px-2 py-1">Dato Técnico / Auditoría de Seguridad (Acceso exclusivo ACT-03)</span></td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                        </div>



                        <!-- 1.3 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">1.3</span>

                                Finalidades Específicas del Tratamiento

                            </h3>

                            <div class="space-y-3">

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-sm font-bold text-slate-900 mb-1">1. Intermediación Laboral Juvenil</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Habilitar la vinculación operativa entre perfiles de jóvenes candidatos (entre 16 y 25 años) y las vacantes publicadas por las empresas registradas en el Valle de Aburrá, permitiendo a los reclutadores autorizados la evaluación curricular y de competencias del postulante.

                                    </p>

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-sm font-bold text-slate-900 mb-1">2. Gestión de Cuentas, Autenticación y Soporte</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Validar el registro y el inicio de sesión seguro en la plataforma conforme a la matriz de roles y permisos (ACT-01 Candidato, ACT-02 Empresa, ACT-03 Administrador); coordinar el restablecimiento de credenciales mediante servidores SMTP autorizados; y transmitir alertas internas del sistema sobre novedades en los procesos de selección.

                                    </p>

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-sm font-bold text-slate-900 mb-1">3. Seguridad, Control de Acceso y Auditoría del Sistema</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Conservar registros inalterables de las operaciones sensibles en la tabla audit_logs (como la descarga de hojas de vida, actualización de estados, modificación de ofertas e inicios de sesión fallidos o exitosos), capturando la dirección IP y datos del navegador para salvaguardar la integridad de la infraestructura, prevenir fraudes y responder ante las autoridades competentes.

                                    </p>

                                </div>

                            </div>

                        </div>



                        <!-- 1.4 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">1.4</span>

                                Régimen Especial de Tratamiento de Datos de Menores de Edad (16 y 17 años)

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-3">

                                Considerando que la población objetivo primordial de la plataforma YEB comprende a jóvenes adolescentes a partir de los 16 años de edad, el tratamiento de sus datos personales se ajusta rigurosamente a las protecciones reforzadas del ordenamiento jurídico colombiano:

                            </p>

                            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 mb-4">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-amber-900 mb-1">Prevalencia de Derechos Fundamentales</h4>

                                <p class="text-xs text-amber-950 leading-relaxed">

                                    De conformidad con el artículo 7 de la Ley Estatutaria 1581 de 2012 y el Código de la Infancia y la Adolescencia (Ley 1098 de 2006), el tratamiento de datos de menores responderá y respetará en todo momento sus derechos prevalentes y el interés superior del menor. Queda terminantemente prohibido el tratamiento de datos personales de carácter sensible de adolescentes sin el consentimiento expreso de sus representantes.

                                </p>

                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-4">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">Flujo Operativo de Autorización Parental y Representación Legal [HU-021]</h4>

                                <p class="text-xs text-slate-600 leading-relaxed mb-3">

                                    Para dar cumplimiento a la Historia de Usuario [HU-021] y resolver el hallazgo técnico de seguridad [SEC009], el sistema exige la captura operacional de la minoría de edad mediante el campo de fecha de nacimiento (users.birthdate). Cuando el sistema detecte que el registrante tiene 16 o 17 años, se activará el siguiente protocolo electrónico:

                                </p>

                                <ol class="space-y-2 text-xs text-slate-600 list-decimal pl-4">

                                    <li><strong>Requisito de Formulario Especial:</strong> El sistema solicitará los datos del representante legal, padre, madre o acudiente (nombre completo, tipo y número de documento de identidad, parentesco y correo electrónico de verificación).</li>

                                    <li><strong>Mecanismo de Verificación y Otorgamiento:</strong> Se enviará una comunicación electrónica con un enlace seguro y token único al acudiente, o en su defecto, el formulario exigirá adjuntar la autorización digital firmada y la copia de la identificación del representante legal.</li>

                                    <li><strong>Registro y Trazabilidad:</strong> El consentimiento otorgado se almacenará en el sistema asociando la fecha, la hora, la dirección IP y la versión exacta de la política aceptada. Sin la verificación previa de este consentimiento, el sistema no habilitará la opción de postulación a vacantes laborales para el menor de edad.</li>

                                </ol>

                            </div>

                        </div>



                        <!-- 1.5 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">1.5</span>

                                Procedimiento para el Ejercicio de Derechos PQRS (Conocer, Actualizar, Rectificar y Suprimir)

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-3">

                                Los titulares de los datos personales tienen el derecho inalienable de conocer, actualizar, rectificar y solicitar la supresión de sus datos, así como de revocar la autorización otorgada mediante el procedimiento formal de Peticiones, Quejas, Reclamos y Sugerencias (PQRS).

                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">Canales de Atención Habilitados</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed mb-2">

                                        Las solicitudes de Hábeas Data deberán remitirse por escrito a la Oficina Interna de Protección de Datos de YEB a través del correo <a href="mailto:soporte-yeb@sena.edu.co" class="font-bold underline text-[#17457c]">soporte-yeb@sena.edu.co</a> o mediante radicado escrito en el SENA CTMA.

                                    </p>

                                    <p class="text-[11px] text-slate-500">Debe contener: nombre del titular, documento, descripción clara de la petición, canal de notificación y documentos soporte.</p>

                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">Plazos Legales de Respuesta (Ley 1581)</h4>

                                    <p class="text-xs text-slate-600 mb-2">

                                        <strong>Consultas (Art. 14):</strong> Máximo diez (10) días hábiles contados a partir de la fecha de recibo. Prórroga máxima de cinco (5) días hábiles si no fuere posible atenderla inicialmente.

                                    </p>

                                    <p class="text-xs text-slate-600">

                                        <strong>Reclamos (Art. 15):</strong> Máximo quince (15) días hábiles. Si es incompleto, se requerirá en cinco (5) días para subsanar. Prórroga máxima de ocho (8) días hábiles adicionales.

                                    </p>

                                </div>

                            </div>

                            <div class="space-y-3">

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Recurso ante la Autoridad de Control (SIC)</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        En cumplimiento del artículo 16 de la Ley 1581 de 2012, se informa expresamente al titular que solo podrá acudir ante la Superintendencia de Industria y Comercio (SIC) - Delegatura para la Protección de Datos Personales, una vez haya agotado el trámite de consulta o reclamo de manera directa ante los Responsables del Tratamiento de la plataforma YEB.

                                    </p>

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Supresión Física de Archivos vs. Conservación Legítima en Auditoría</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Al procesar la eliminación de una cuenta o el retiro de documentos, el sistema ejecuta la supresión física de los archivos en disco (storage/cvs y storage/avatars) y borra la información de perfil en users. Sin embargo, en atención al interés legítimo de seguridad de la información, trazabilidad de incidentes e investigaciones técnico-legales, los registros técnicos en la tabla audit_logs se conservarán disociados por un periodo estandarizado de diez (10) años (resolviendo la condición técnica "por definir" de [DAT09]), tras lo cual se ejecutarán tareas automáticas de depuración de logs.

                                    </p>

                                </div>

                            </div>

                        </div>



                        <!-- 1.6 -->

                        <div>

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">1.6</span>

                                Transferencia, Transmisión, Circulación Restringida y Medidas de Seguridad

                            </h3>

                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4 mb-4">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-[#17457c] mb-1">Regla de Circulación Restringida (RN-010)</h4>

                                <p class="text-xs text-slate-700 leading-relaxed">

                                    En estricta observancia de la regla de negocio [RN-010], los datos personales de contacto, perfil y la hoja de vida adjunta de un joven candidato no son de libre acceso público ni serán indexados en motores de búsqueda externos. Dicha información solo será accesible y descargable para las empresas registradas a cuyas vacantes el candidato se haya postulado voluntariamente. Un reclutador o empresa sin postulación activa previa por parte del candidato tiene técnicamente denegada la visualización o descarga de la documentación personal del joven.

                                </p>

                            </div>

                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">Medidas Técnicas y Mecanismos de Seguridad Implementados</h4>

                            <ul class="space-y-2 text-xs text-slate-600 pl-4 list-disc">

                                <li><strong>Cifrado Criptográfico de Contraseñas:</strong> Las contraseñas se almacenan mediante el algoritmo de hash criptográfico no reversible bcrypt utilizando la función nativa password_hash de PHP 8.2, asegurando que las credenciales nunca reposen en texto plano [RNF-003], [SEC001].</li>

                                <li><strong>Almacenamiento Privado de Archivos:</strong> Las hojas de vida (storage/cvs), fotos de perfil (storage/avatars) y logos corporativos (storage/company-avatars) se alojan en un directorio privado fuera de la raíz pública del servidor web Apache (public_html). Su entrega se realiza indirectamente mediante controladores PHP que verifican la sesión activa, el rol y la existencia de la relación postulación-vacante antes de transmitir los bytes del archivo [ADR004], [SEC002].</li>

                                <li><strong>Obfuscación de Archivos Subidos:</strong> Todo archivo cargado se renombra inmediatamente con una cadena aleatoria de 48 caracteres de alta entropía para prevenir la enumeración de archivos o la ejecución remota de comandos.</li>

                                <li><strong>Control de Acceso Estricto por Rol en Servidor:</strong> La autorización de peticiones se valida centralizadamente en el servidor (controlador frontal CMPPORTAL), asegurando que ningún usuario ejecute acciones o acceda a recursos prohibidos para su rol [DRV-006].</li>

                                <li><strong>Protección contra Inyección SQL y XSS:</strong> El acceso a la base de datos yeb_portal mediante la interfaz PDO deshabilita explícitamente la emulación de consultas preparadas [SEC003]. Toda salida de datos hacia la interfaz gráfica se sanitiza con htmlspecialchars para mitigar ataques XSS [SEC007].</li>

                            </ul>

                        </div>

                    </section>



                    <!-- ==============================================

                         SECCIÓN 2: POLÍTICA DE COOKIES

                         ============================================== -->

                    <section id="seccion-2" class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-10 shadow-sm">

                        <div class="flex items-center gap-3 border-b border-slate-100 pb-5 mb-6">

                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white font-extrabold text-sm shadow-sm">

                                2

                            </span>

                            <div>

                                <span class="text-[10px] font-bold uppercase tracking-wider text-[#17457c]">Transparencia Digital y Privacidad Técnica</span>

                                <h2 class="display text-2xl md:text-3xl font-extrabold text-slate-900">

                                    2. SECCIÓN II: POLÍTICA DE COOKIES Y TECNOLOGÍAS DE RASTREO

                                </h2>

                            </div>

                        </div>



                        <!-- 2.1 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">2.1</span>

                                Definición, Tipos y Clasificación de Cookies Utilizadas

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-4">

                                La plataforma YEB utiliza cookies y tecnologías de almacenamiento local para garantizar la seguridad de las transacciones, la autenticación y la operatividad de la interfaz. Se clasifican técnicamente de la siguiente manera:

                            </p>

                            <div class="space-y-3 mb-4">

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/70">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-1">1. Cookies Técnicas y Estrictamente Necesarias (Esenciales)</h4>

                                    <p class="text-xs text-slate-600 mb-2">Son indispensables para la prestación del servicio web y la navegación segura. Su desactivación impide el funcionamiento del software:</p>

                                    <ul class="space-y-1.5 text-xs text-slate-600 pl-4 list-disc">

                                        <li><strong>Session Cookie (PHP Native):</strong> Cookie de sesión que mantiene la autenticación del usuario mientras navega en el aplicativo. Se destruye al ejecutar el cierre de sesión (APIAUTHLOGOUT). Requerimiento de seguridad abierto: En el ambiente de producción, la cookie de sesión debe configurarse obligatoriamente con los atributos HttpOnly, Secure y SameSite=Lax [SEC004].</li>

                                        <li><strong>Token CSRF (Cross-Site Request Forgery):</strong> Token criptográfico aleatorio de 64 caracteres hex generado por sesión e introducido en los formularios POST. Se valida mediante comparaciones de tiempo constante (hash_equals) para evitar peticiones maliciosas forzadas desde sitios externos [SEC008].</li>

                                    </ul>

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/70">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-1">2. Tecnologías Funcionales y Almacenamiento Local</h4>

                                    <p class="text-xs text-slate-600">

                                        <strong>Preferencias del Candidato:</strong> Registros en las tablas asociativas saved_jobs y hidden_jobs para gestionar vacantes favoritas y ocultas vinculadas al perfil activo [RF-014].

                                    </p>

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/70">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-1">3. Carga de Recursos Externos</h4>

                                    <p class="text-xs text-slate-600">

                                        <strong>Redes de Distribución de Contenido (CDN):</strong> La plataforma consume archivos de estilo y fuentes a través de las redes CDN públicas Tailwind CSS y Google Fonts.

                                    </p>

                                </div>

                            </div>

                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 text-xs text-emerald-950 font-medium">

                                <strong>Declaración Expresa:</strong> La plataforma YEB no integra pasarelas de pago ni utiliza cookies publicitarias, de rastreo comercial, de analítica comportamental o de terceros para perfilamiento comercial.

                            </div>

                        </div>



                        <!-- 2.2 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">2.2</span>

                                Banner de Consentimiento de Dos Capas y Prohibición de Consentimiento Tácito

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-3">

                                Para asegurar el consentimiento informado e inequívoco sobre el uso de tecnologías de almacenamiento local y cookies no esenciales, YEB implementa un Banner de Consentimiento de Dos Capas:

                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">1. Primera Capa (Aviso Principal)</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Al acceder a la plataforma por primera vez, se despliega un banner visible informando el uso de cookies técnicas necesarias para la navegación segura y la carga de recursos estéticos externos, disponiendo de botones con texto claro y explícito: "Aceptar Todas" y "Configurar/Rechazar".

                                    </p>

                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">2. Segunda Capa (Panel de Configuración)</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Permite al usuario habilitar o inhabilitar de forma granular la carga de recursos no esenciales o consultar los detalles de cada tecnología empleada.

                                    </p>

                                </div>

                            </div>

                            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-rose-900 mb-1">Prohibición Absoluta del Consentimiento Tácito</h4>

                                <p class="text-xs text-rose-950 leading-relaxed">

                                    De conformidad con las directrices internacionales y nacionales de protección de datos, la inacción, el desplazamiento vertical por la pantalla (scroll), la navegación continuada o el silencio del usuario no constituyen en ningún caso una manifestación válida de consentimiento. Si el titular no otorga una aceptación explícita mediante un clic inequívoco, la plataforma únicamente activará aquellas cookies técnicas estrictamente necesarias para el funcionamiento del servicio.

                                </p>

                            </div>

                        </div>



                        <!-- 2.3 -->

                        <div>

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">2.3</span>

                                Guía de Desactivación y Control desde el Navegador

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-4">

                                El usuario tiene la facultad de revocar su consentimiento, bloquear o borrar las cookies instaladas por YEB ajustando la configuración de su navegador web. Se presentan las guías paso a paso para los navegadores soportados:

                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-600 mb-4">

                                <div class="rounded-2xl border border-slate-200 p-3.5 bg-slate-50/50">

                                    <strong class="text-slate-900 block mb-1">Google Chrome:</strong>

                                    1. Diríjase a Configuración &gt; Privacidad y seguridad.<br>

                                    2. Haga clic en Cookies y otros datos de sitios.<br>

                                    3. Seleccione la opción de bloqueo deseada o busque el dominio de YEB para eliminar sus cookies.

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-3.5 bg-slate-50/50">

                                    <strong class="text-slate-900 block mb-1">Microsoft Edge:</strong>

                                    1. Ingrese a Configuración &gt; Cookies y permisos del sitio.<br>

                                    2. Seleccione Administrar y eliminar cookies y datos del sitio.<br>

                                    3. Gestione los permisos o elimine los datos almacenados.

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-3.5 bg-slate-50/50">

                                    <strong class="text-slate-900 block mb-1">Mozilla Firefox:</strong>

                                    1. Abra Ajustes &gt; Privacidad &amp; Seguridad.<br>

                                    2. En Cookies y datos del sitio, haga clic en Limpiar datos.<br>

                                    3. Confirme la eliminación de las cookies asociadas a la plataforma.

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-3.5 bg-slate-50/50">

                                    <strong class="text-slate-900 block mb-1">Apple Safari:</strong>

                                    1. Entre a Preferencias &gt; Privacidad.<br>

                                    2. Seleccione Gestionar datos del sitio web o active la casilla Bloquear todas las cookies.

                                </div>

                            </div>

                            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 text-xs text-amber-950">

                                <strong>Efectos del Bloqueo Técnico:</strong> La desactivación de las cookies técnicas esenciales (Session Cookie y Token CSRF) provocará la pérdida del estado de autenticación en la plataforma, impidiendo el envío de formularios POST, el registro de postulaciones y el acceso a los paneles privados de usuario.

                            </div>

                        </div>

                    </section>



                    <!-- ==============================================

                         SECCIÓN 3: TÉRMINOS Y CONDICIONES DE USO

                         ============================================== -->

                    <section id="seccion-3" class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-10 shadow-sm">

                        <div class="flex items-center gap-3 border-b border-slate-100 pb-5 mb-6">

                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-[#17457c] to-[#0f2d52] text-white font-extrabold text-sm shadow-sm">

                                3

                            </span>

                            <div>

                                <span class="text-[10px] font-bold uppercase tracking-wider text-[#17457c]">Condiciones Vinculantes del Servicio</span>

                                <h2 class="display text-2xl md:text-3xl font-extrabold text-slate-900">

                                    3. SECCIÓN III: TÉRMINOS Y CONDICIONES DE USO

                                </h2>

                                <p class="text-xs text-slate-500 font-semibold">(LEY 1480 DE 2011 Y LEY 2300 DE 2023)</p>

                            </div>

                        </div>



                        <!-- 3.1 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">3.1</span>

                                Naturaleza del Servicio, Gratuidad y Reglas de Registro por Rol

                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Propósito y Alcance Geográfico</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        Youth Employment Bridge (YEB) es una plataforma web de intermediación laboral juvenil diseñada para articular la oferta y demanda de empleo en el Valle de Aburrá (Colombia), dirigida a jóvenes de 16 a 25 años y a las micro y pequeñas empresas locales.

                                    </p>

                                </div>

                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-900 mb-1">Regla Absoluta de Gratuidad del Servicio [EXC-01]</h4>

                                    <p class="text-xs text-emerald-950 leading-relaxed">

                                        En cumplimiento de la regla de exclusión [EXC-01], la plataforma YEB es 100% gratuita tanto para los candidatos jóvenes como para las empresas o reclutadores. El sistema no cuenta con pasarela de pagos, ni cobra tarifas por suscripción, cobros por publicación de ofertas, comisiones por contratación ni cobros por la elaboración de hojas de vida digitales.

                                    </p>

                                </div>

                            </div>



                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">Reglas de Registro y Matriz de Roles y Permisos</h4>

                            <div class="overflow-x-auto rounded-2xl border border-slate-200 mb-2">

                                <table class="w-full text-left text-xs text-slate-600">

                                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">

                                        <tr>

                                            <th class="p-3.5 w-1/4">Rol de Usuario</th>

                                            <th class="p-3.5">Permisos, Capacidades y Reglas de Operación</th>

                                        </tr>

                                    </thead>

                                    <tbody class="divide-y divide-slate-100">

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Joven / Candidato (ACT-01)</td>

                                            <td class="p-3.5 space-y-1">

                                                <p>&bull; Permite la creación de la hoja de vida digital [RF-005], carga del archivo adjunto (PDF recomendado, hasta 5 MB) [RF-007], edición de perfil [RF-006], búsqueda con filtros [RF-012], marcado de vacantes favoritas u ocultas [RF-014] y seguimiento de postulaciones [RF-016].</p>

                                                <p>&bull; <strong>Regla RN-005 (Unicidad de Postulación):</strong> El candidato solo podrá postularse una sola vez a la misma vacante. El servicio valida y bloquea automáticamente cualquier intento duplicado, respaldado por la clave única UNIQUE(job_id, user_id) en la tabla applications [CR-002].</p>

                                                <p>&bull; Tiene denegada la publicación de vacantes y la administración global del aplicativo.</p>

                                            </td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Empresa / Reclutador (ACT-02)</td>

                                            <td class="p-3.5 space-y-1">

                                                <p>&bull; <strong>Regla RN-003 (Perfil Obligatorio con NIT):</strong> Ninguna empresa podrá publicar vacantes laborales sin registrar previamente su Razón Social completa y su Número de Identificación Tributaria (NIT) en el perfil corporativo [RF-008]. Aviso de brecha técnica: El modelo de datos actual carece del campo nit en la tabla companies, lo cual constituye una tarea de migración pendiente para garantizar el cumplimiento estricto de [RN-003].</p>

                                                <p>&bull; Permite la publicación, edición y cierre de ofertas [RF-010], [RF-011], definiendo la modalidad (Remoto, Híbrido, Presencial) y el salario (rango definido o "a convenir" [CR-001]).</p>

                                                <p>&bull; Permite la consulta de candidatos postulados y la descarga autorizada de sus hojas de vida [RF-017], [RN-010]. Tiene restringida la postulación a empleos.</p>

                                            </td>

                                        </tr>

                                        <tr class="hover:bg-slate-50/70">

                                            <td class="p-3.5 font-bold text-slate-900">Administrador (ACT-03)</td>

                                            <td class="p-3.5 space-y-1">

                                                <p>&bull; Supervisión general de la plataforma, gestión de cuentas [RF-022], moderación de vacantes [RF-023] y consulta de métricas y auditoría filtrable [RF-021].</p>

                                                <p>&bull; Tiene prohibido eliminarse a sí mismo o postularse a ofertas laborales.</p>

                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                        </div>



                        <!-- 3.2 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">3.2</span>

                                Veracidad de Vacantes, Declaración de Responsabilidad y Moderación

                            </h3>

                            <div class="space-y-3">

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Responsabilidad Exclusiva de las Empresas (RN-008)</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        En virtud de la regla de negocio [RN-008], las empresas y reclutadores registrados son los únicos y exclusivos responsables por la veracidad, exactitud, vigencia, licitud y condiciones reales de trabajo ofrecidas en las vacantes publicadas en YEB. La plataforma actúa como un canal tecnológico neutro de intermediación laboral y no asume responsabilidad alguna por las condiciones laborales reales o los compromisos pactados entre el empleador y el postulante.

                                    </p>

                                </div>

                                <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-rose-900 mb-1">Facultades Administrativas de Moderación (RN-007)</h4>

                                    <p class="text-xs text-slate-700 leading-relaxed mb-2">

                                        De conformidad con la regla [RN-007], el equipo administrador de YEB se reserva el derecho técnico y legal de ejercer acciones de moderación ante el incumplimiento de estos Términos:

                                    </p>

                                    <ul class="space-y-1 text-xs text-slate-600 pl-4 list-disc">

                                        <li><strong>Suspender o Eliminar Cuentas:</strong> Inhabilitar el acceso a empresas que suministren información falsa, omitan el NIT, suplanten personas jurídicas o vulneren la privacidad de los jóvenes.</li>

                                        <li><strong>Retirar Vacantes Fraudulentas:</strong> Inactivar o eliminar de forma inmediata cualquier vacante engañosa, que requiera dinero o cobros a los jóvenes para participar en el proceso de selección, que promueva actividades al margen de la ley o que vulnere la dignidad del postulante.</li>

                                    </ul>

                                </div>

                            </div>

                        </div>



                        <!-- 3.3 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">3.3</span>

                                Propiedad Intelectual, Licencia de Uso y Limitación de Responsabilidad

                            </h3>

                            <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50 mb-4">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Propiedad Intelectual del Software</h4>

                                <p class="text-xs text-slate-600 leading-relaxed">

                                    Los derechos de propiedad intelectual sobre el código fuente (monolito modular desarrollado en PHP 8.2), la estructura de la base de datos yeb_portal, los diseños de interfaz UX/UI, logotipos, marcas y manuales técnicos corresponden de forma exclusiva al equipo desarrollador (SENA CTMA). La aceptación de estos términos otorga al usuario una licencia de uso personal, no exclusiva, revocable, gratuita e intransferible para hacer uso de la plataforma según los permisos de su rol.

                                </p>

                            </div>

                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2">Limitaciones Operativas y Exclusiones Directas del Servicio</h4>

                            <p class="text-xs text-slate-600 leading-relaxed mb-3">

                                Para todos los efectos legales, la plataforma YEB declara expresamente las siguientes exclusiones operativas de responsabilidad:

                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-600">

                                <div class="rounded-2xl border border-slate-200 p-3 bg-white">

                                    <strong class="text-slate-900 block mb-1">&bull; Garantía de Contratación:</strong>

                                    YEB no garantiza la convocatoria efectiva a entrevistas ni la contratación final de los jóvenes postulados.

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-3 bg-white">

                                    <strong class="text-slate-900 block mb-1">&bull; Firma de Contratos Laborales [EXC-04]:</strong>

                                    La plataforma no ofrece funcionalidades para la firma digital o electrónica de contratos de trabajo. Todo vínculo contractual se perfecciona fuera del sistema entre empleador y trabajador.

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-3 bg-white">

                                    <strong class="text-slate-900 block mb-1">&bull; Coordinación de Entrevistas [EXC-02]:</strong>

                                    La plataforma no integra herramientas de videollamada, videoconferencia ni chat en tiempo real. La cita para entrevistas presenciales o virtuales será gestionada de manera externa e independiente por el reclutador.

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-3 bg-white">

                                    <strong class="text-slate-900 block mb-1">&bull; Pruebas Psicotécnicas [EXC-03]:</strong>

                                    El sistema no aplica evaluaciones psicológicas, pruebas de personalidad ni exámenes de competencias; cada empresa ejecutará sus instrumentos de evaluación de manera independiente.

                                </div>

                            </div>

                        </div>



                        <!-- 3.4 -->

                        <div class="mb-8">

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">3.4</span>

                                Estatuto del Consumidor: Procedencia del Derecho de Retracto y Reversión de Pago (Ley 1480 de 2011)

                            </h3>

                            <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50 mb-3">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Análisis sobre la Reversión del Pago y el Retracto Financiero</h4>

                                <p class="text-xs text-slate-600 leading-relaxed mb-2">

                                    Dado que la plataforma YEB opera bajo el principio de gratuidad absoluta para candidatos y empresas [EXC-01], sin realizar cobros, suscripciones ni integrar pasarelas de pago ni transacciones monetarias:

                                </p>

                                <ul class="space-y-1 text-xs text-slate-600 pl-4 list-disc">

                                    <li><strong>Reversión del Pago (Art. 51, Ley 1480 de 2011):</strong> Resulta inaplicable legal y operativamente, toda vez que no se efectúan debitaciones o pagos con tarjetas de crédito, débito o instrumentos financieros dentro de la plataforma que pudiesen ser objeto de reembolso.</li>

                                    <li><strong>Derecho de Retracto Financiero (Art. 47, Ley 1480 de 2011):</strong> Tampoco resulta aplicable por la ausencia de pagos o sumas dinerarias entregadas a YEB.</li>

                                </ul>

                            </div>

                            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4">

                                <h4 class="text-xs font-bold uppercase tracking-wider text-[#17457c] mb-1">Procedimiento Equivalente para el "Retracto del Servicio"</h4>

                                <p class="text-xs text-slate-700 leading-relaxed mb-2">

                                    Sin perjuicio de lo anterior, YEB garantiza el derecho equivalente al retracto del servicio mediante la cancelación voluntaria de la cuenta y supresión de datos:

                                </p>

                                <ul class="space-y-1 text-xs text-slate-600 pl-4 list-disc">

                                    <li>Cualquier usuario podrá solicitar en cualquier momento la baja de su cuenta y la supresión de su perfil mediante las funciones de perfil o enviando una solicitud formal a Hábeas Data.</li>

                                    <li>El sistema ejecutará la eliminación de los archivos en disco (storage/cvs y storage/avatars) y la desactivación del perfil, dando por terminado el servicio de intermediación de forma inmediata.</li>

                                </ul>

                            </div>

                        </div>



                        <!-- 3.5 -->

                        <div>

                            <h3 class="text-lg font-bold text-slate-900 mb-3 flex items-center gap-2">

                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-100 text-[#17457c] text-xs font-bold">3.5</span>

                                Límites de Contacto Comercial y Canales Autorizados (Ley 2300 de 2023 - "Dejen de Fregar")

                            </h3>

                            <p class="text-sm text-slate-600 leading-relaxed mb-3">

                                En estricto cumplimiento de la Ley 2300 de 2023, que protege el derecho a la intimidad, el descanso y el silencio de los ciudadanos frente a contactos comerciales o informativos, la plataforma YEB establece la siguiente regulación de notificaciones:

                            </p>

                            <div class="space-y-3 mb-4">

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Canales Autorizados y Frecuencia de Contacto</h4>

                                    <p class="text-xs text-slate-600 mb-1">YEB únicamente enviará comunicaciones transaccionales e informativas a través de dos medios:</p>

                                    <ol class="space-y-1 text-xs text-slate-600 pl-4 list-decimal">

                                        <li><strong>Alertas Internas en Plataforma (notifications):</strong> Mensajes visibles en la interfaz privada al iniciar sesión [RF-020].</li>

                                        <li><strong>Correo Electrónico Transaccional:</strong> Mensajes vía servidor SMTP a la dirección electrónica registrada por el usuario [RSD-05].</li>

                                    </ol>

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Distinción entre Mensajes Transaccionales y Comunicaciones Informativas</h4>

                                    <ul class="space-y-1.5 text-xs text-slate-600 pl-4 list-disc">

                                        <li><strong>Mensajes Transaccionales (Exentos de Restricción Horaria):</strong> Son aquellas comunicaciones generadas por la acción directa e inmediata del usuario (ejemplo: solicitud de recuperación de contraseña [RF-004], alertas críticas de seguridad de la cuenta, confirmación de registro). Estos correos se transmiten en tiempo real sin restricción de horario por ser indispensables para el uso del servicio.</li>

                                        <li><strong>Comunicaciones Informativas y Alertas de Ofertas (Sujetas a Horario Legal):</strong> Las notificaciones masivas, recomendaciones de vacantes afines o resúmenes de actividad solo podrán enviarse en las franjas horarias legales establecidas en la Ley 2300 de 2023: de lunes a viernes entre las 8:00 a.m. y las 7:00 p.m., y los sábados de 8:00 a.m. a 3:00 p.m. Queda totalmente prohibido el envío de correos informativos o comerciales los días domingos y festivos.</li>

                                    </ul>

                                </div>

                                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">

                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-1">Configuración de Preferencias por el Usuario</h4>

                                    <p class="text-xs text-slate-600 leading-relaxed">

                                        El usuario podrá en todo momento configurar sus preferencias de notificaciones desde el panel de perfil, pudiendo desactivar la recepción de correos de recomendaciones o alertas no esenciales, sin que ello afecte la recepción de las comunicaciones transaccionales indispensables para la seguridad de la cuenta.

                                    </p>

                                </div>

                            </div>

                        </div>

                    </section>

                </div>



                <!-- Footer Call to Action -->

                <div class="mt-8 rounded-3xl border border-blue-100 bg-gradient-to-br from-blue-50 to-indigo-50/50 p-6 sm:p-8 text-center">

                    <h3 class="display text-xl font-extrabold text-[#17457c] mb-2">Canal Institucional de Atención</h3>

                    <p class="text-xs sm:text-sm text-slate-600 max-w-xl mx-auto mb-4">

                        Para cualquier consulta formal sobre esta política, ejercicio de derechos de Hábeas Data o PQRS, comunícate con el equipo responsable.

                    </p>

                    <a href="mailto:soporte-yeb@sena.edu.co" class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] text-white px-5 py-2.5 text-xs font-bold hover:bg-[#12365f] shadow-md shadow-blue-900/10 transition">

                        ' . self::iconMail('w-4 h-4 text-white') . ' soporte-yeb@sena.edu.co

                    </a>

                </div>

            </div>

        </div>';

    }


    /**

     * Dato suelto de los perfiles públicos compartidos entre empresa y candidato.

     */

    protected static function publicFact(string $label, ?string $value): string
    {

        if ($value === null || trim($value) === '') {

            return '';

        }



        return '

        <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3">

            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">' . self::e($label) . '</p>

            <p class="mt-1 text-sm font-semibold text-slate-800 break-words">' . self::e($value) . '</p>

        </div>';

    }


    /**

     * Bloque de texto largo de los perfiles públicos compartidos.

     */

    protected static function publicSection(string $title, ?string $value): string
    {

        if ($value === null || trim($value) === '') {

            return '';

        }



        return '

        <article class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm">

            <h2 class="text-base font-bold text-slate-900">' . self::e($title) . '</h2>

            <p class="mt-3 text-sm text-slate-600 leading-relaxed whitespace-pre-line">' . self::e($value) . '</p>

        </article>';

    }


    protected static function alerts(?string $message, ?string $error): string
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


    protected static function field(string $name, string $label, string $type = 'text', string $placeholder = ''): string
    {

        $labelHtml = '<label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">' . $label . '</label>';

        if ($type === 'textarea') {

            return '<div>' . $labelHtml . '<textarea required name="' . $name . '" placeholder="' . self::e($placeholder) . '" class="w-full rounded-xl border border-slate-200 p-3 text-sm text-slate-800 min-h-[120px] focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent"></textarea></div>';

        }

        return '<div>' . $labelHtml . '<input required name="' . $name . '" type="' . $type . '" placeholder="' . self::e($placeholder) . '" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent"></div>';

    }


    protected static function fieldValue(string $name, string $label, ?string $value, string $type = 'text'): string
    {

        $value = self::e($value ?? '');

        $labelHtml = '<label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">' . $label . '</label>';

        if ($type === 'textarea') {

            return '<div class="md:col-span-2">' . $labelHtml . '<textarea name="' . $name . '" class="w-full rounded-xl border border-slate-200 p-3 text-sm text-slate-800 min-h-[100px] focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent">' . $value . '</textarea></div>';

        }

        return '<div>' . $labelHtml . '<input name="' . $name . '" type="' . $type . '" value="' . $value . '" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent"></div>';

    }


    /**

     * Formulario para actualizar la contraseña de la cuenta con sesión activa.

     */

    protected static function passwordChangeForm(): string
    {

        return '

        <form method="post" action="?action=change_password" class="mt-5 grid gap-4 md:grid-cols-2">

            <input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">

            <div class="md:col-span-2">

                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contraseña actual</label>

                <input required type="password" name="current_password" autocomplete="current-password" placeholder="••••••••" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

            </div>

            <div>

                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nueva contraseña</label>

                <input required type="password" name="new_password" autocomplete="new-password" minlength="6" placeholder="Mínimo 6 caracteres" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

            </div>

            <div>

                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Confirmar nueva contraseña</label>

                <input required type="password" name="password_confirm" autocomplete="new-password" minlength="6" placeholder="Repite la nueva contraseña" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#17457c] focus:border-transparent transition">

            </div>

            <div class="md:col-span-2 flex justify-end">

                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#17457c] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#12365f]">

                    ' . self::iconLock('w-4 h-4') . ' Actualizar contraseña

                </button>

            </div>

        </form>';

    }


    /**

     * Evita enviar el formulario cuando la contraseña y su confirmación no coinciden.

     */

    protected static function passwordMatchScript(): string
    {

        return '

        <script>

        (function () {

            var forms = document.querySelectorAll("form");

            Array.prototype.forEach.call(forms, function (form) {

                var confirmField = form.querySelector("input[name=\'password_confirm\']");

                if (!confirmField) return;

                var passwordField = form.querySelector("input[name=\'new_password\']") || form.querySelector("input[name=\'password\']");

                if (!passwordField) return;

                var error = document.createElement("p");

                error.className = "mt-1.5 text-xs font-semibold text-rose-600";

                error.style.display = "none";

                confirmField.parentNode.appendChild(error);

                form.addEventListener("submit", function (event) {

                    if (passwordField.value !== confirmField.value) {

                        event.preventDefault();

                        error.textContent = "Las contraseñas no coinciden.";

                        error.style.display = "block";

                        confirmField.focus();

                        return;

                    }

                    error.style.display = "none";

                });

            });

        })();

        </script>';

    }


    /**

     * URL pública de un archivo estático del proyecto.

     *

     * El portal puede publicarse usando la raíz del proyecto como docroot o

     * solo la carpeta public/, así que la ruta se calcula a partir del script

     * que se está ejecutando.

     */

    protected static function assetUrl(string $path): string
    {

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

        $folder = basename(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))));

        $public = $base === '' ? '' : ($folder === 'public' ? $base : $base . '/public');



        return $public . '/assets/' . ltrim($path, '/');

    }


    protected static function e(string $value): string
    {

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    }


    protected static function iconSearch(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>';

    }


    protected static function iconLocation(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>';

    }


    protected static function iconBell(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>';

    }


    protected static function iconHeart(bool $filled = false, string $class = 'w-5 h-5'): string
    {

        return $filled

            ? '<svg class="' . $class . ' text-rose-500 fill-current inline-block" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>'

            : '<svg class="' . $class . ' text-slate-400 hover:text-rose-500 transition inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>';

    }


    protected static function iconBriefcase(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';

    }


    protected static function iconClock(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

    }


    protected static function iconCalendar(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';

    }


    protected static function iconCurrency(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

    }


    protected static function iconShare(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>';

    }


    protected static function iconEyeOff(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>';

    }


    protected static function iconCheck(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';

    }


    protected static function iconUser(string $class = 'w-6 h-6'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>';

    }


    protected static function iconDocument(string $class = 'w-6 h-6'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';

    }


    protected static function iconBuilding(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>';

    }


    protected static function iconShield(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>';

    }


    protected static function iconChart(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>';

    }


    protected static function iconPlus(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>';

    }


    protected static function iconMail(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';

    }


    protected static function iconLock(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>';

    }


    protected static function iconArrowRight(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>';

    }


    protected static function iconArrowLeft(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>';

    }


    protected static function iconDownload(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>';

    }


    protected static function iconSpark(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>';

    }


    protected static function iconAlert(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';

    }


    protected static function iconTrash(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';

    }


    protected static function iconFilter(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>';

    }


    protected static function iconUserPlus(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>';

    }


    protected static function iconChat(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>';

    }


    protected static function iconCamera(string $class = 'w-5 h-5'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8a2 2 0 012-2h1.5l1.2-2h8.6l1.2 2H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/><circle cx="12" cy="12.5" r="3.5"/></svg>';

    }


    protected static function iconUpload(string $class = 'w-4 h-4'): string
    {

        return '<svg class="' . $class . '" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>';

    }
}
