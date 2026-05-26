<?php declare(strict_types=1);

namespace App\ApiResource\Notification;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Notification\NotificationMarkAllReadProcessor;
use App\State\Processor\Notification\NotificationReadProcessor;
use App\State\Provider\Notification\NotificationCollectionProvider;
use App\State\Provider\Notification\NotificationItemProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'UserNotification',
    operations: [
        new GetCollection(
            uriTemplate: '/user/notifications',
            openapi: new Operation(tags: ['Notification']),
            paginationEnabled: true,
            paginationItemsPerPage: 20,
            paginationMaximumItemsPerPage: 50,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_notifications_get_collection',
            provider: NotificationCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/user/notifications/{id}',
            uriVariables: ['id'],
            requirements: ['id' => '[0-9a-fA-F\-]{36}'],
            openapi: new Operation(tags: ['Notification']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_notifications_get_item',
            provider: NotificationItemProvider::class,
        ),
        new Post(
            uriTemplate: '/user/notifications/{id}/read',
            uriVariables: ['id'],
            requirements: ['id' => '[0-9a-fA-F\-]{36}'],
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Notification']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            read: false,
            deserialize: false,
            validate: false,
            name: 'api_user_notifications_read',
            processor: NotificationReadProcessor::class,
        ),
        new Post(
            uriTemplate: '/user/notifications/mark-all-read',
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Notification']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            read: false,
            deserialize: false,
            validate: false,
            name: 'api_user_notifications_mark_all_read',
            processor: NotificationMarkAllReadProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class UserNotification
{
    #[ApiProperty(identifier: true)]
    public string $id;

    public string $type;

    /** @var array<string, mixed> */
    public array $payload = [];

    public ?string $readDatetime = null;

    public string $creationDatetime;
}
