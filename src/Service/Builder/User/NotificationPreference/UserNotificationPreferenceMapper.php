<?php

declare(strict_types=1);

namespace App\Service\Builder\User\NotificationPreference;

use App\ApiResource\User\NotificationPreference\UserNotificationPreferenceEdit;
use App\Entity\User\UserNotificationPreference;

/** One list of the switches, read and written the same way by the GET and the PATCH. */
final class UserNotificationPreferenceMapper
{
    private const array FIELDS = [
        'siteNews',
        'weeklyRecap',
        'messageReceived',
        'publicationComment',
        'forumReply',
        'marketing',
        'activityReminder',
        'showOnlinePresence',
        'pushMessageReceived',
        'pushPublicationComment',
        'pushForumReply',
        'pushModeration',
        'pushBandChat',
        'pushBandMention',
        'pushBandTasks',
        'pushBandAgenda',
        'pushBandFinance',
        'pushBandMembership',
    ];

    public static function toResource(UserNotificationPreference $preference): UserNotificationPreferenceEdit
    {
        $resource = new UserNotificationPreferenceEdit();
        foreach (self::FIELDS as $field) {
            $resource->{$field} = $preference->{$field};
        }

        return $resource;
    }

    public static function apply(UserNotificationPreferenceEdit $resource, UserNotificationPreference $preference): void
    {
        foreach (self::FIELDS as $field) {
            $preference->{$field} = $resource->{$field};
        }
    }
}
