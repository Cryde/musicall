<?php

declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\ApiResource\Notification\UserNotification;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;

/**
 * Refreshes `payload.wall_username` to the wall owner's username as it is now (#1316): the link opens
 * /u/{username}, and a username can change. One query for the page. A deleted post keeps the stored
 * name, which still opens the wall unless it changed too. Subclasses bind the type.
 */
abstract readonly class AbstractProfilePostEnricher
{
    public function __construct(private ProfilePostRepository $profilePostRepository)
    {
    }

    /**
     * @param UserNotification[] $notifications
     */
    public function enrich(array $notifications, User $recipient): void
    {
        $ids = [];
        foreach ($notifications as $notification) {
            $postId = $notification->payload['post_id'] ?? null;
            if (is_string($postId)) {
                $ids[$postId] = $postId;
            }
        }

        $usernames = [];
        foreach ($this->profilePostRepository->findWithAuthorsByIds(array_values($ids)) as $post) {
            $usernames[(string) $post->id] = $post->author->username;
        }

        foreach ($notifications as $notification) {
            $postId = $notification->payload['post_id'] ?? null;
            if (is_string($postId) && isset($usernames[$postId])) {
                $notification->payload['wall_username'] = $usernames[$postId];
            }
        }
    }
}
