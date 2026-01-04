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

    public ?int $pendingGalleries = null;

    public ?int $pendingPublications = null;
}
