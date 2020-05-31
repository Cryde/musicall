<?php

namespace App\Service\Formatter\Message;

use App\Entity\User;

class MessageUserSenderFormatter
{
    public function formatList(array $messages, User $sender): array
    {
        $result = [];
        foreach ($messages as $message) {
            $result[] = $this->format($message, $sender);
        }

        return $result;
    }

    public function format(array $message, User $sender): array
    {
        $isSender = $message['author']['username'] === $sender->getUsername();

        return array_merge(['is_sender' => $isSender], $message);
    }
}
