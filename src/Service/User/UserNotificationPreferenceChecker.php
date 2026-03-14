<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;

readonly class UserNotificationPreferenceChecker
{
    public function canReceiveSiteNewsNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference === null || $preference->siteNews;
    }

    public function canReceiveWeeklyRecapNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference === null || $preference->weeklyRecap;
    }

    public function canReceiveMessageNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference === null || $preference->messageReceived;
    }

    public function canReceivePublicationCommentNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference === null || $preference->publicationComment;
    }

    public function canReceiveForumReplyNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference === null || $preference->forumReply;
    }

    public function canReceiveMarketingNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference !== null && $preference->marketing;
    }

    public function canReceiveActivityReminderNotification(User $user): bool
    {
        $preference = $user->notificationPreference;

        return $preference === null || $preference->activityReminder;
    }
}
