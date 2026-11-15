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
            // No URI variable, so API Platform would not read by default: every switch left out of the
            // merge patch then fell back to its default, wiping a preference an older app does not know.
            read: true,
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

    public bool $publicationVote = true;

    public bool $forumReply = true;

    public bool $marketing = false;

    public bool $activityReminder = true;

    public bool $showOnlinePresence = true;

    public bool $showReadReceipts = true;

    // Push categories (#1110), see PushCategory.
    public bool $pushMessageReceived = true;

    public bool $pushPublicationComment = true;

    public bool $pushForumReply = true;

    public bool $pushModeration = true;

    public bool $pushBandChat = true;

    public bool $pushBandMention = true;

    public bool $pushBandTasks = true;

    public bool $pushBandAgenda = true;

    public bool $pushBandFinance = true;

    public bool $pushBandMembership = true;
}
