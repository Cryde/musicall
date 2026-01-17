<?php

declare(strict_types=1);

namespace App\ApiResource\User\NotificationPreference;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\NotificationPreference\UserNotificationPreferenceEditProcessor;
use App\State\Provider\User\NotificationPreference\UserNotificationPreferenceEditProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/user/notification-preferences',
            openapi: new Operation(tags: ['Notification Preferences']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_notification_preferences_get',
            provider: UserNotificationPreferenceEditProvider::class,
        ),
        new Patch(
            uriTemplate: '/user/notification-preferences',
            openapi: new Operation(tags: ['Notification Preferences']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_notification_preferences_edit',
            provider: UserNotificationPreferenceEditProvider::class,
            processor: UserNotificationPreferenceEditProcessor::class,
        ),
    ]
)]
class UserNotificationPreferenceEdit
{
    public bool $siteNews = true;

    public bool $weeklyRecap = true;

    public bool $messageReceived = true;

    public bool $publicationComment = true;

    public bool $forumReply = true;

    public bool $marketing = false;

    public bool $activityReminder = true;
}
