<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Enum\Notification\PushCategory;

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

    /** Absent preferences mean the default, which is shown. */
    public function showsOnlinePresence(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->showOnlinePresence;
    }

    public function canReceiveActivityReminderNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $preference->activityReminder;
    }

    /** Absent preferences mean the defaults, under which every category is on. */
    public function canReceivePush(User $user, PushCategory $category): bool
    {
        $preference = $user->notificationPreference;

        return !$preference instanceof \App\Entity\User\UserNotificationPreference || $category->isEnabledIn($preference);
    }
}
