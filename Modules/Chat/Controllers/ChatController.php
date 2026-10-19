<?php

namespace Modules\Chat\Controllers;

use Modules\Chat\Services\ChatService;

final class ChatController
{
    public function __construct(private ChatService $chat)
    {
    }

    public function chat(int $applicationId, array $user): array
    {
        return $this->chat->getChat($applicationId, $user);
    }

    public function send(int $applicationId, array $user, string $message): array
    {
        return $this->chat->sendMessage($applicationId, $user, $message);
    }

    public function conversations(array $user): array
    {
        return $this->chat->conversations($user);
    }

    public function unreadCount(array $user): int
    {
        return $this->chat->unreadCount($user);
    }
}
