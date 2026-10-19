<?php

declare(strict_types=1);

// Autoloading
spl_autoload_register(function (string $class): void {
    $prefixes = ['App\\' => __DIR__ . '/../app/', 'Modules\\' => __DIR__ . '/../Modules/'];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
        }
    }
});

use Modules\Auditoria\Services\AuditService;
use Modules\Chat\Controllers\ChatController;
use Modules\Chat\Repositories\ChatRepository;
use Modules\Chat\Services\ChatService;
use Modules\Notifications\Services\NotificationService;

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAILED: $message\n";
        exit(1);
    }
    echo "PASSED: $message\n";
}

function assertThrows(callable $fn, string $expectedException, string $message): void {
    try {
        $fn();
        echo "FAILED (No exception thrown): $message\n";
        exit(1);
    } catch (\Throwable $e) {
        if ($e instanceof $expectedException) {
            echo "PASSED: $message (" . get_class($e) . ": " . $e->getMessage() . ")\n";
        } else {
            echo "FAILED (Unexpected exception " . get_class($e) . "): $message\n";
            exit(1);
        }
    }
}

// Create a Mock ChatRepository for isolated testing
class MockChatRepository extends ChatRepository {
    public array $applications = [];
    public array $messages = [];
    public array $readCalls = [];

    public function findApplication(int $applicationId): ?array {
        return $this->applications[$applicationId] ?? null;
    }

    public function sendMessage(int $applicationId, int $senderId, string $message): int {
        $id = count($this->messages) + 1;
        $this->messages[] = [
            'id' => $id,
            'application_id' => $applicationId,
            'sender_id' => $senderId,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null,
            'sender_name' => 'User ' . $senderId,
            'sender_role' => 'candidate',
            'sender_photo' => null,
        ];
        return $id;
    }

    public function getMessages(int $applicationId): array {
        return array_values(array_filter($this->messages, fn($m) => $m['application_id'] === $applicationId));
    }

    public function markAsRead(int $applicationId, int $currentUserId): void {
        $this->readCalls[] = ['app' => $applicationId, 'user' => $currentUserId];
    }

    public function getCompanyRecruiterUserIds(int $companyId): array {
        return [10];
    }

    public function getConversationsForCandidate(int $userId): array {
        return [
            [
                'application_id' => 1,
                'status' => 'interview',
                'applied_at' => '2026-10-10',
                'job_id' => 101,
                'job_title' => 'Frontend Developer',
                'company_id' => 5,
                'company_name' => 'Tech Corp',
                'company_city' => 'Bogota',
                'last_message' => 'Hola',
                'last_message_at' => '2026-10-11 10:00:00',
                'unread_count' => 1,
            ]
        ];
    }

    public function getConversationsForCompany(int $companyId, int $recruiterUserId): array {
        return [
            [
                'application_id' => 1,
                'status' => 'interview',
                'applied_at' => '2026-10-10',
                'job_id' => 101,
                'job_title' => 'Frontend Developer',
                'candidate_id' => 2,
                'candidate_name' => 'Juan Perez',
                'candidate_email' => 'juan@test.com',
                'last_message' => 'Hola',
                'last_message_at' => '2026-10-11 10:00:00',
                'unread_count' => 0,
            ]
        ];
    }

    public function unreadCountForUser(array $user): int {
        return 1;
    }
}

echo "=== INICIANDO PRUEBAS DEL MÓDULO DE CHAT ===\n\n";

$repo = new MockChatRepository();
$service = new ChatService($repo);
$controller = new ChatController($service);

// Set up sample application data
$repo->applications[1] = [
    'id' => 1,
    'job_id' => 101,
    'candidate_id' => 2,
    'status' => 'interview',
    'created_at' => '2026-10-10 10:00:00',
    'job_title' => 'Frontend Developer',
    'company_id' => 5,
    'company_name' => 'Tech Corp',
    'candidate_name' => 'Juan Perez',
    'candidate_email' => 'juan@test.com',
];

$repo->applications[2] = [
    'id' => 2,
    'job_id' => 102,
    'candidate_id' => 2,
    'status' => 'rejected', // Postulación rechazada
    'created_at' => '2026-10-10 11:00:00',
    'job_title' => 'Backend Developer',
    'company_id' => 5,
    'company_name' => 'Tech Corp',
    'candidate_name' => 'Juan Perez',
    'candidate_email' => 'juan@test.com',
];

$candidateUser = ['id' => 2, 'role' => 'candidate', 'name' => 'Juan Perez'];
$recruiterUser = ['id' => 10, 'role' => 'recruiter', 'company_id' => 5, 'name' => 'Tech Corp RRHH'];
$otherCandidate = ['id' => 99, 'role' => 'candidate', 'name' => 'Otro'];
$otherRecruiter = ['id' => 88, 'role' => 'recruiter', 'company_id' => 999, 'name' => 'Otra Empresa'];

// Test 1: Access control
echo "\n--- Prueba 1: Control de Acceso ---\n";
$chat = $controller->chat(1, $candidateUser);
assertTrue($chat['can_send'] === true, 'Candidato legítimo puede ver el chat');

$chatRecruiter = $controller->chat(1, $recruiterUser);
assertTrue($chatRecruiter['can_send'] === true, 'Reclutador dueño de la vacante puede ver el chat');

assertThrows(fn() => $controller->chat(1, $otherCandidate), RuntimeException::class, 'Otro candidato no puede acceder al chat');
assertThrows(fn() => $controller->chat(1, $otherRecruiter), RuntimeException::class, 'Otra empresa no puede acceder al chat');

// Test 2: Sending messages when application is in normal status (interview)
echo "\n--- Prueba 2: Envío de Mensajes en postulación activa ---\n";
$msg = $controller->send(1, $candidateUser, 'Hola, tengo una consulta sobre la vacante.');
assertTrue($msg['id'] === 1, 'Candidato envía mensaje correctamente');

$msgRecruiter = $controller->send(1, $recruiterUser, 'Hola Juan, dinos tu consulta.');
assertTrue($msgRecruiter['id'] === 2, 'Reclutador envía mensaje correctamente');

// Test 3: CRITICAL REQUIREMENT - REJECTED APPLICATION MUST BLOCK MESSAGES
echo "\n--- Prueba 3: Postulación Rechazada bloquea envío de mensajes ---\n";
$chatRejected = $controller->chat(2, $candidateUser);
assertTrue($chatRejected['can_send'] === false, 'can_send es false cuando la postulación está rechazada');

assertThrows(
    fn() => $controller->send(2, $candidateUser, 'Intento de mensaje cuando está rechazada'),
    DomainException::class,
    'Candidato NO puede enviar mensaje si la postulación está rechazada'
);

assertThrows(
    fn() => $controller->send(2, $recruiterUser, 'Intento de empresa cuando está rechazada'),
    DomainException::class,
    'Reclutador NO puede enviar mensaje si la postulación está rechazada'
);

// Test 4: Validation (empty message, whitespace)
echo "\n--- Prueba 4: Validación de Mensaje Vacío ---\n";
assertThrows(
    fn() => $controller->send(1, $candidateUser, '   '),
    InvalidArgumentException::class,
    'No se puede enviar mensaje vacío o con solo espacios'
);

// Test 5: Conversations & unread count
echo "\n--- Prueba 5: Listado de Conversaciones y Contadores ---\n";
$candConvs = $controller->conversations($candidateUser);
assertTrue(count($candConvs) === 1, 'Candidato obtiene su lista de conversaciones');
$recConvs = $controller->conversations($recruiterUser);
assertTrue(count($recConvs) === 1, 'Empresa obtiene su lista de conversaciones');
assertTrue($controller->unreadCount($candidateUser) === 1, 'Conteo de mensajes no leídos funciona');

echo "\nTODAS LAS PRUEBAS PASARON EXITOSAMENTE (100% OK).\n";
