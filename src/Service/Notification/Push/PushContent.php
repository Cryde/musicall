<?php declare(strict_types=1);

namespace App\Service\Notification\Push;

/** What a push shows and where a tap leads, before it is queued for one recipient. */
final readonly class PushContent
{
    /**
     * @param array<non-empty-string, string> $data            FCM only accepts string values
     * @param string|null                     $conversationKey groups the pushes of one conversation (#1378), see PushNotifier
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data,
        public ?string $conversationKey = null,
    ) {
    }
}
