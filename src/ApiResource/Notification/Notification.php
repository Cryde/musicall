<?php

declare(strict_types=1);

namespace App\ApiResource\Notification;

use ApiPlatform\Metadata\Get;
use App\State\Provider\Notification\NotificationProvider;

#[Get(
    name: 'api_notifications_get',
    provider: NotificationProvider::class,
)]
class Notification
{
    public int $unreadMessages = 0;

    /**
     * Unread in each Band Space chat, keyed by band space id, for the sidebar badge (#962). Separate
     * from unreadMessages on purpose: that one is direct messages, and its inbox cannot show or clear
     * a band's conversation. A space with nothing unread is absent, so the client reads it with `?? 0`.
     *
     * @var array<string, int>
     */
    public array $bandSpaceChatUnread = [];

    public ?int $pendingGalleries = null;

    public ?int $pendingPublications = null;

    /** Untriaged feedback. Null for anyone but an admin, like the two counts above. */
    public ?int $newFeedbacks = null;
}
