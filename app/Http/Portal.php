<?php

namespace App\Http;

use App\Core\Database;
use Modules\Auth\Services\AuthService;
use Modules\Auditoria\Services\AuditService;
use Modules\Jobs\Repositories\JobRepository;
use Modules\Jobs\Services\JobService;
use Throwable;

final class Portal
{
    public static function run(): void
    {
        $action = $_GET['action'] ?? 'home';
        $user = $_SESSION['user'] ?? null;
        $error = null;
        $message = null;
        $database = Database::connection();
        $audit = new AuditService();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
                throw new \RuntimeException('La sesión del formulario expiró. Recarga la página.');
            }
            if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!(new AuthService())->login($_POST['email'] ?? '', $_POST['password'] ?? '')) $error = 'Correo o contraseña incorrectos.';
                else { header('Location: ./?action=dashboard'); exit; }
            } elseif ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                if (strlen($_POST['password'] ?? '') < 6) $error = 'La contraseña debe tener al menos 6 caracteres.';
                else { (new AuthService())->register($_POST['name'], $_POST['email'], $_POST['password']); $message = 'Cuenta creada. Ya puedes ingresar.'; $action = 'login'; }
            } elseif ($action === 'logout') {
                if ($user) $audit->log('logout', 'Auth', 'Cierre de sesión.', 'success', $user['id']);
                session_destroy(); header('Location: ./'); exit;
            } elseif ($action === 'create_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                foreach (['title', 'description', 'city', 'experience', 'salary_min', 'salary_max'] as $field) if (trim((string) ($_POST[$field] ?? '')) === '') throw new \InvalidArgumentException('Completa todos los campos de la vacante.');
                $statement = $database->prepare('INSERT INTO jobs (company_id,title,description,city,work_mode,experience,salary_min,salary_max,status) VALUES (:company,:title,:description,:city,:mode,:experience,:min,:max,:status)');
                $workMode = ['hybrid' => 'Hybrid', 'remote' => 'Remote', 'onsite' => 'Presencial'][$_POST['work_mode']] ?? 'Hybrid';
                $statement->execute(['company' => $user['company_id'], 'title' => trim($_POST['title']), 'description' => trim($_POST['description']), 'city' => trim($_POST['city']), 'mode' => $workMode, 'experience' => trim($_POST['experience']), 'min' => (float) $_POST['salary_min'], 'max' => (float) $_POST['salary_max'], 'status' => $_POST['status'] === 'draft' ? 'draft' : 'published']);
                $audit->log('job_created', 'Jobs', 'La empresa creó una vacante.', 'success', $user['id'], ['job_id' => (int) $database->lastInsertId()]);
                $message = 'Vacante creada correctamente.'; $action = 'dashboard';
            } elseif ($action === 'apply' && $user && $user['role'] === 'candidate') {
                $statement = $database->prepare('INSERT INTO applications (job_id,user_id) VALUES (:job,:user)');
                $statement->execute(['job' => (int) $_GET['job'], 'user' => $user['id']]);
                $audit->log('application_created', 'Applications', 'El candidato envió una postulación.', 'success', $user['id'], ['job_id' => (int) $_GET['job']]);
                $message = 'Postulación enviada correctamente.'; $action = 'dashboard';
            } elseif ($action === 'update_application' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                self::role($user, 'recruiter');
                $statement = $database->prepare('UPDATE applications a JOIN jobs j ON j.id=a.job_id SET a.status=:status WHERE a.id=:application AND j.company_id=:company');
                $statement->execute(['status' => $_POST['status'], 'application' => (int) $_POST['application_id'], 'company' => $user['company_id']]);
                $audit->log('application_status_updated', 'Applications', 'La empresa actualizó una postulación.', 'success', $user['id'], ['application_id' => (int) $_POST['application_id'], 'status' => $_POST['status']]);
                $message = 'Estado actualizado.'; $action = 'dashboard';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            if ($user) $audit->log('action_error', 'System', 'Acción rechazada: ' . substr($exception->getMessage(), 0, 180), 'error', $user['id'], ['action' => $action]);
        }

        $jobs = (new JobService(new JobRepository()))->search($_GET['q'] ?? '', $_GET['location'] ?? '');
        $content = $action === 'dashboard' && $user ? self::dashboard($database, $user, $message, $error) : self::home($jobs, $user, $action, $message, $error);
        echo self::layout($content, $user);
    }

    private static function role(?array $user, string $role): void
    {
        if (!$user || $user['role'] !== $role || ($role === 'recruiter' && empty($user['company_id']))) throw new \RuntimeException('No tienes permisos para realizar esta acción.');
    }

    private static function dashboard($db, array $user, ?string $message, ?string $error): string
    {
        $html = self::alerts($message, $error);
        if ($user['role'] === 'admin') {
            $cards = '';
            foreach (['users' => 'users', 'companies' => 'companies', 'jobs' => 'jobs', 'applications' => 'applications'] as $label => $table) $cards .= '<div class="rounded-2xl border bg-white p-5"><p class="text-sm capitalize text-slate-500">' . self::e($label) . '</p><p class="mt-2 text-3xl font-bold">' . $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() . '</p></div>';
            $logs = $db->query('SELECT audit_logs.*,users.name user_name FROM audit_logs LEFT JOIN users ON users.id=audit_logs.user_id ORDER BY audit_logs.created_at DESC LIMIT 100')->fetchAll();
            $rows = '';
            foreach ($logs as $log) $rows .= '<tr class="border-t"><td class="px-3 py-3 text-xs">' . self::e($log['created_at']) . '</td><td class="px-3 py-3">' . self::e($log['user_name'] ?? 'Sistema') . '</td><td class="px-3 py-3 font-bold">' . self::e($log['action']) . '</td><td class="px-3 py-3">' . self::e($log['module']) . '</td><td class="px-3 py-3">' . self::e($log['description']) . '</td><td class="px-3 py-3">' . self::e($log['ip_address']) . '</td></tr>';
            return $html . '<div class="mb-8"><p class="font-bold uppercase tracking-widest text-[#fca311]">Administración</p><h1 class="display text-4xl font-bold">Panel de auditoría</h1><p class="mt-2 text-slate-500">Logs de acciones, respuestas y actividad del sistema.</p></div><div class="mb-8 grid gap-4 md:grid-cols-4">' . $cards . '</div><div class="overflow-x-auto rounded-2xl border bg-white p-4"><h2 class="display mb-4 text-2xl font-bold">Logs recientes</h2><table class="w-full min-w-[850px] text-left text-sm"><thead><tr class="text-xs uppercase text-slate-500"><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Usuario</th><th class="px-3 py-2">Acción</th><th class="px-3 py-2">Módulo</th><th class="px-3 py-2">Descripción</th><th class="px-3 py-2">IP</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
        }
        if ($user['role'] === 'recruiter') {
            $company = $db->prepare('SELECT * FROM companies WHERE id=:id'); $company->execute(['id' => $user['company_id']]); $company = $company->fetch();
            $jobs = $db->prepare('SELECT jobs.*,COUNT(applications.id) applications_count FROM jobs LEFT JOIN applications ON applications.job_id=jobs.id WHERE jobs.company_id=:company GROUP BY jobs.id ORDER BY jobs.created_at DESC'); $jobs->execute(['company' => $user['company_id']]);
            $jobRows = ''; foreach ($jobs as $job) $jobRows .= '<tr class="border-t"><td class="px-3 py-3 font-bold">' . self::e($job['title']) . '</td><td class="px-3 py-3">' . self::e($job['status']) . '</td><td class="px-3 py-3">' . $job['applications_count'] . '</td></tr>';
            $applications = $db->prepare('SELECT applications.id,applications.status,jobs.title,users.name candidate_name,users.email FROM applications JOIN jobs ON jobs.id=applications.job_id JOIN users ON users.id=applications.user_id WHERE jobs.company_id=:company ORDER BY applications.created_at DESC'); $applications->execute(['company' => $user['company_id']]);
            $applicationRows = ''; foreach ($applications as $item) $applicationRows .= '<tr class="border-t"><td class="px-3 py-3">' . self::e($item['candidate_name']) . '<br><span class="text-xs text-slate-500">' . self::e($item['email']) . '</span></td><td class="px-3 py-3">' . self::e($item['title']) . '</td><td class="px-3 py-3"><form method="post" action="?action=update_application" class="flex gap-2"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><input type="hidden" name="application_id" value="' . $item['id'] . '"><select name="status" class="rounded border p-1"><option value="pending">Pendiente</option><option value="review">En revisión</option><option value="interview">Entrevista</option><option value="accepted">Aceptado</option><option value="rejected">Rechazado</option></select><button class="font-bold text-blue-600">Guardar</button></form></td></tr>';
            return $html . '<div class="mb-8"><p class="font-bold uppercase tracking-widest text-[#fca311]">Empresa</p><h1 class="display text-4xl font-bold">' . self::e($company['name']) . '</h1><p class="mt-2 text-slate-500">Crea publicaciones y gestiona candidatos.</p></div><div class="grid gap-8 lg:grid-cols-[.8fr_1.2fr]"><form method="post" action="?action=create_job" class="rounded-2xl border bg-white p-6"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '"><h2 class="display mb-5 text-2xl font-bold">Crear vacante</h2>' . self::field('title', 'Título') . self::field('description', 'Descripción', 'textarea') . self::field('city', 'Ciudad') . '<div class="grid grid-cols-2 gap-3">' . self::field('experience', 'Experiencia') . self::field('salary_min', 'Salario mínimo', 'number') . '</div>' . self::field('salary_max', 'Salario máximo', 'number') . '<label class="mt-4 block text-sm font-bold">Modalidad<select name="work_mode" class="mt-1 w-full rounded-lg border p-3"><option value="hybrid">Híbrido</option><option value="remote">Remoto</option><option value="onsite">Presencial</option></select></label><label class="mt-4 block text-sm font-bold">Estado<select name="status" class="mt-1 w-full rounded-lg border p-3"><option value="published">Publicar ahora</option><option value="draft">Guardar borrador</option></select></label><button class="mt-5 w-full rounded-lg bg-[#14213d] p-3 font-bold text-white">Crear vacante</button></form><div class="space-y-8"><div class="rounded-2xl border bg-white p-6"><h2 class="display mb-4 text-2xl font-bold">Mis vacantes</h2><table class="w-full text-left text-sm"><thead><tr class="text-slate-500"><th class="px-3 py-2">Vacante</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Postulaciones</th></tr></thead><tbody>' . $jobRows . '</tbody></table></div><div class="rounded-2xl border bg-white p-6"><h2 class="display mb-4 text-2xl font-bold">Candidatos</h2><div class="overflow-x-auto"><table class="w-full min-w-[600px] text-left text-sm"><thead><tr class="text-slate-500"><th class="px-3 py-2">Candidato</th><th class="px-3 py-2">Vacante</th><th class="px-3 py-2">Estado</th></tr></thead><tbody>' . $applicationRows . '</tbody></table></div></div></div></div>';
        }
        $stmt = $db->prepare('SELECT applications.*,jobs.title,companies.name company_name FROM applications JOIN jobs ON jobs.id=applications.job_id JOIN companies ON companies.id=jobs.company_id WHERE applications.user_id=:user ORDER BY applications.created_at DESC'); $stmt->execute(['user' => $user['id']]); $rows = '';
        foreach ($stmt->fetchAll() as $item) $rows .= '<div class="flex items-center justify-between border-t py-4"><div><p class="font-bold">' . self::e($item['title']) . '</p><p class="text-sm text-slate-500">' . self::e($item['company_name']) . '</p></div><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">' . self::e($item['status']) . '</span></div>';
        return $html . '<div class="mb-8"><p class="font-bold uppercase tracking-widest text-blue-600">Dashboard</p><h1 class="display text-4xl font-bold">Tu actividad</h1></div><div class="rounded-2xl border bg-white p-6"><h2 class="display mb-5 text-2xl font-bold">Historial de postulaciones</h2>' . ($rows ?: '<p class="text-slate-500">Aún no tienes postulaciones.</p>') . '</div>';
    }

    private static function home(array $jobs, ?array $user, string $action, ?string $message, ?string $error): string
    {
        $alerts = self::alerts($message, $error);
        if ($action === 'login' || $action === 'register') return $alerts . '<section class="mx-auto max-w-md rounded-2xl border bg-white p-8 shadow-sm"><h1 class="display mb-6 text-4xl font-bold">' . ($action === 'login' ? 'Ingresar' : 'Crear perfil') . '</h1><form method="post" class="space-y-4"><input type="hidden" name="csrf" value="' . self::e($_SESSION['csrf']) . '">' . ($action === 'register' ? '<input required name="name" placeholder="Nombre completo" class="w-full rounded-lg border p-3"><input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3">' : '<input required name="email" type="email" placeholder="Correo electrónico" class="w-full rounded-lg border p-3">') . '<input required name="password" type="password" placeholder="Contraseña" class="w-full rounded-lg border p-3"><button class="w-full rounded-lg bg-blue-600 p-3 font-bold text-white">Continuar</button></form></section>';
        $cards = ''; foreach ($jobs as $job) $cards .= '<article class="rounded-2xl border bg-white p-6 shadow-sm"><span class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700">' . self::e($job['work_mode']) . '</span><h3 class="mt-5 text-xl font-bold">' . self::e($job['title']) . '</h3><p class="mt-2 text-sm font-medium text-slate-500">' . self::e($job['company_name']) . ' · ' . self::e($job['city']) . '</p><p class="mt-4 text-sm leading-6 text-slate-600">' . self::e($job['description']) . '</p><div class="mt-6 border-t pt-4"><span class="text-sm font-bold">$' . number_format((float) $job['salary_min'], 0, ',', '.') . ' - $' . number_format((float) $job['salary_max'], 0, ',', '.') . '</span>' . ($user && $user['role'] === 'candidate' ? '<a href="?action=apply&job=' . $job['id'] . '" class="float-right font-bold text-blue-600">Postularme →</a>' : '<a href="?action=login" class="float-right font-bold text-blue-600">Ingresar →</a>') . '</div></article>';
        return $alerts . '<section class="grid items-center gap-10 py-10 md:grid-cols-[1.1fr_.9fr]"><div><p class="mb-4 text-sm font-bold uppercase tracking-[.2em] text-blue-600">Youth Employment Bridge</p><h1 class="display text-5xl font-bold leading-tight md:text-6xl">Encuentra un trabajo que te haga avanzar.</h1><p class="mt-5 max-w-xl text-lg leading-8 text-slate-600">Vacantes reales, primeras oportunidades y empresas que apuestan por tu talento.</p></div><div class="rounded-3xl bg-[#14213d] p-8 text-white"><p class="text-sm text-[#fca311]">Para candidatos y empresas</p><p class="display mt-3 text-3xl font-bold">Tu talento tiene un lugar.</p></div></section><section id="vacantes" class="py-8"><div class="mb-6 flex items-end justify-between"><div><p class="font-bold uppercase tracking-widest text-blue-600">Oportunidades</p><h2 class="display text-3xl font-bold">Vacantes para ti</h2></div><span class="text-sm text-slate-500">' . count($jobs) . ' resultados</span></div><form class="mb-8 grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-[1fr_1fr_auto]"><input name="q" value="' . self::e($_GET['q'] ?? '') . '" placeholder="Cargo, habilidad o empresa" class="rounded-lg border p-3"><input name="location" value="' . self::e($_GET['location'] ?? '') . '" placeholder="Ciudad o modalidad" class="rounded-lg border p-3"><button class="rounded-lg bg-blue-600 px-6 font-bold text-white">Buscar</button></form><div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">' . $cards . '</div></section>';
    }

    private static function layout(string $content, ?array $user): string
    {
        $nav = $user ? '<a href="?action=dashboard" class="font-bold">' . self::e($user['name']) . '</a><a href="?action=logout" class="rounded-lg border px-3 py-2">Salir</a>' : '<a href="?action=login">Ingresar</a><a href="?action=register" class="rounded-lg bg-blue-600 px-4 py-2 font-bold text-white">Crear perfil</a>';
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>YEB | Youth Employment Bridge</title><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"><style>body{font-family:DM Sans,sans-serif}.display{font-family:Fraunces,serif}</style></head><body class="bg-[#f8fafc] text-[#14213d]"><header class="sticky top-0 z-10 border-b bg-white/95"><div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4"><a href="./" class="display text-xl font-bold">YEB<span class="text-blue-600">.</span></a><nav class="flex items-center gap-4 text-sm">' . $nav . '</nav></div></header><main class="mx-auto max-w-6xl px-5 py-12">' . $content . '</main><footer class="border-t bg-white"><div class="mx-auto max-w-6xl px-5 py-6 text-sm text-slate-500">YEB · Youth Employment Bridge · Portal de empleo modular</div></footer></body></html>';
    }

    private static function alerts(?string $message, ?string $error): string { return ($error ? '<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">' . self::e($error) . '</div>' : '') . ($message ? '<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">' . self::e($message) . '</div>' : ''); }
    private static function field(string $name, string $label, string $type = 'text'): string { return $type === 'textarea' ? '<label class="mt-4 block text-sm font-bold">' . $label . '<textarea required name="' . $name . '" class="mt-1 min-h-24 w-full rounded-lg border p-3"></textarea></label>' : '<label class="mt-4 block text-sm font-bold">' . $label . '<input required name="' . $name . '" type="' . $type . '" class="mt-1 w-full rounded-lg border p-3"></label>'; }
    private static function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
