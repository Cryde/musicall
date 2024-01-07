<?php

namespace App\ApiResource\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Provider\Notification\NotificationProvider;
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
    private int $unreadMessages = 0;
    #[Groups([Notification::ITEM_ADMIN])]
    private int $pendingGalleries = 0;
    #[Groups([Notification::ITEM_ADMIN])]
    private int $pendingPublications = 0;

    public function getUnreadMessages(): int
    {
        return $this->unreadMessages;
    }

    public function setUnreadMessages(int $unreadMessages): Notification
    {
        $this->unreadMessages = $unreadMessages;

        return $this;
    }

    /**
     * @return int
     */
    public function getPendingGalleries(): int
    {
        return $this->pendingGalleries;
    }

    public function setPendingGalleries(int $pendingGalleries): Notification
    {
        $this->pendingGalleries = $pendingGalleries;

        return $this;
    }

    public function getPendingPublications(): int
    {
        return $this->pendingPublications;
    }

    public function setPendingPublications(int $pendingPublications): Notification
    {
        $this->pendingPublications = $pendingPublications;

        return $this;
    }
}