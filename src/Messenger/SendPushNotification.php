<?php declare(strict_types=1);

namespace App\Messenger;

use App\Enum\Notification\PushCategory;

/**
 * Push a notification to every device of one user, off the request. Carries the user id rather than
 * the entity so the worker reloads it and skips an account deleted in between.
 */
final class SendPushNotification
{
    /**
     * Groups the pushes of one conversation (#1378). Not readonly, unlike the rest: a readonly property
     * cannot have a default, and a message queued before it existed is unserialized without it.
     */
    public private(set) ?string $conversationKey = null;

    /**
     * @param array<non-empty-string, string> $data     FCM only accepts string values
     * @param PushCategory|null               $category the switch it sits behind, checked when sent; null
     *                                                  for a push nobody can turn off (app:push:test)
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly ?PushCategory $category = null,
        ?string $conversationKey = null,
    ) {
        $this->conversationKey = $conversationKey;
    }
}
