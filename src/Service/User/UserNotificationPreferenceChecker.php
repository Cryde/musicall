<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;

readonly class UserNotificationPreferenceChecker
{
    public function canReceiveSiteNewsNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->siteNews;
    }

    public function canReceiveWeeklyRecapNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->weeklyRecap;
    }

    public function canReceiveMessageNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->messageReceived;
    }

    public function canReceivePublicationCommentNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->publicationComment;
    }

    public function canReceiveForumReplyNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->forumReply;
    }

    public function canReceiveMarketingNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference instanceof \App\Entity\User\UserNotificationPreference && $preference->marketing;
    }

    public function canReceiveActivityReminderNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->activityReminder;
    }
}
