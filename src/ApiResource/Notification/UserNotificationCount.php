<?php declare(strict_types=1);

namespace App\ApiResource\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Notification\NotificationCountProvider;

#[ApiResource(
    shortName: 'UserNotificationCount',
    operations: [
        new Get(
            uriTemplate: '/user/notifications/count',
            openapi: new Operation(tags: ['Notification']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_notifications_count',
            provider: NotificationCountProvider::class,
        ),
    ],
)]
class UserNotificationCount
{
    public int $unread = 0;
}
