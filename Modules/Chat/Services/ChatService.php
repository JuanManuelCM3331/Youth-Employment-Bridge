<?php

namespace Modules\Chat\Services;

use Modules\Auditoria\Services\AuditService;
use Modules\Chat\Repositories\ChatRepository;
use Modules\Notifications\Services\NotificationService;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class ChatService
{
    public function __construct(
        private ChatRepository $repository,
        private ?NotificationService $notifications = null,
        private ?AuditService $audit = null
    ) {
    }

    public function getApplication(int $applicationId, array $user): array
    {
        $application = $this->repository->findApplication($applicationId);
        if (!$application) {
            throw new InvalidArgumentException('Postulación no encontrada.');
        }

        $userRole = $user['role'] ?? '';
        if ($userRole === 'candidate' && (int) $application['candidate_id'] !== (int) $user['id']) {
            throw new RuntimeException('No tienes permiso para acceder a esta conversación.');
        }

        if ($userRole === 'recruiter' && (int) $application['company_id'] !== (int) ($user['company_id'] ?? 0)) {
            throw new RuntimeException('No tienes permiso para acceder a esta conversación.');
        }

        if (!in_array($userRole, ['candidate', 'recruiter', 'admin'], true)) {
            throw new RuntimeException('Acceso no autorizado.');
        }

        return $application;
    }

    public function isChatOpen(array $application): bool
    {
        return ($application['status'] ?? '') === 'interview';
    }

    public function canSendMessage(array $application): bool
    {
        return $this->isChatOpen($application);
    }

    public function getChat(int $applicationId, array $user): array
    {
        $application = $this->getApplication($applicationId, $user);
        $status = $application['status'] ?? '';

        if (in_array($status, ['pending', 'review'], true)) {
            $label = $status === 'pending' ? 'pendiente' : 'en revisión';
            throw new DomainException("El chat no está habilitado para postulaciones en estado {$label}. Solo se abre cuando el candidato pasa a estado de entrevista.");
        }

        $this->repository->markAsRead($applicationId, (int) $user['id']);
        $messages = $this->repository->getMessages($applicationId);
        $canSend = $this->canSendMessage($application);

        return [
            'application' => $application,
            'messages' => $messages,
            'can_send' => $canSend,
        ];
    }

    public function sendMessage(int $applicationId, array $user, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('El mensaje no puede estar vacío.');
        }

        if (mb_strlen($text) > 3000) {
            throw new InvalidArgumentException('El mensaje supera el límite máximo de 3000 caracteres.');
        }

        $application = $this->getApplication($applicationId, $user);

        if (!$this->canSendMessage($application)) {
            $status = $application['status'] ?? '';
            if (in_array($status, ['pending', 'review'], true)) {
                throw new DomainException('No es posible enviar mensajes: el chat no puede estar abierto en estado pendiente ni en revisión.');
            }
            if ($status === 'accepted') {
                throw new DomainException('No es posible enviar mensajes: el canal de chat ha sido cerrado porque la postulación fue aceptada.');
            }
            if ($status === 'rejected') {
                throw new DomainException('No es posible enviar mensajes: la postulación se encuentra rechazada y el chat está cerrado.');
            }
            throw new DomainException('No es posible enviar mensajes en este estado de la postulación.');
        }

        $messageId = $this->repository->sendMessage($applicationId, (int) $user['id'], $text);

        // Enviar notificación a la contraparte
        if ($this->notifications !== null) {
            if (($user['role'] ?? '') === 'candidate') {
                $recruiters = $this->repository->getCompanyRecruiterUserIds((int) $application['company_id']);
                foreach ($recruiters as $recruiterId) {
                    $this->notifications->create(
                        (int) $recruiterId,
                        'Nuevo mensaje de ' . $user['name'] . ' sobre la postulación a ' . $application['job_title'],
                        'Nuevo mensaje en chat',
                        './?action=chat&application_id=' . $applicationId
                    );
                }
            } elseif (($user['role'] ?? '') === 'recruiter') {
                $this->notifications->create(
                    (int) $application['candidate_id'],
                    'Nuevo mensaje de ' . $application['company_name'] . ' sobre tu postulación a ' . $application['job_title'],
                    'Nuevo mensaje en chat',
                    './?action=chat&application_id=' . $applicationId
                );
            }
        }

        $this->audit?->log(
            'chat_message_sent',
            'Chat',
            'Mensaje enviado en la postulación #' . $applicationId,
            'success',
            $user['id'],
            ['application_id' => $applicationId, 'message_id' => $messageId]
        );

        return [
            'id' => $messageId,
            'application_id' => $applicationId,
            'sender_id' => $user['id'],
            'message' => $text,
        ];
    }

    public function conversations(array $user): array
    {
        $role = $user['role'] ?? '';
        if ($role === 'candidate') {
            return $this->repository->getConversationsForCandidate((int) $user['id']);
        } elseif ($role === 'recruiter' && !empty($user['company_id'])) {
            return $this->repository->getConversationsForCompany((int) $user['company_id'], (int) $user['id']);
        }

        return [];
    }

    public function unreadCount(array $user): int
    {
        return $this->repository->unreadCountForUser($user);
    }
}
