<?php

namespace App\ApiResource\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Provider\Notification\NotificationProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => [Notification::ITEM]],
            name: 'api_notifications_get',
            provider: NotificationProvider::class,
        ),
    ]
)]
class Notification
{
    const ITEM = 'ITEM_NOTIFICATION';
    const ITEM_ADMIN = 'ITEM_ADMIN_NOTIFICATION';
    #[Groups([Notification::ITEM])]
    public int $unreadMessages = 0;
    #[Groups([Notification::ITEM_ADMIN])]
    public int $pendingGalleries = 0;
    #[Groups([Notification::ITEM_ADMIN])]
    public int $pendingPublications = 0;
}