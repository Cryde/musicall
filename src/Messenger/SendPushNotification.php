<?php declare(strict_types=1);

namespace App\Messenger;

/**
 * Push a notification to every device of one user, off the request. Carries the user id rather than
 * the entity so the worker reloads it and skips an account deleted in between.
 */
final readonly class SendPushNotification
{
    /**
     * @param array<non-empty-string, string> $data FCM only accepts string values
     */
    public function __construct(
        public string $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {
    }
}
