<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;

readonly class UserNotificationPreferenceChecker
{
    public function canReceiveSiteNewsNotification(User $user): bool
    {
        $preference = $user->getNotificationPreference();

        return $preference === null || $preference->isSiteNews();
    }

    public function canReceiveWeeklyRecapNotification(User $user): bool
    {
        $preference = $user->getNotificationPreference();

        return $preference === null || $preference->isWeeklyRecap();
    }

    public function canReceiveMessageNotification(User $user): bool
    {
        $preference = $user->getNotificationPreference();

        return $preference === null || $preference->isMessageReceived();
    }

    public function canReceivePublicationCommentNotification(User $user): bool
    {
        $preference = $user->getNotificationPreference();

        return $preference === null || $preference->isPublicationComment();
    }

    public function canReceiveForumReplyNotification(User $user): bool
    {
        $preference = $user->getNotificationPreference();

        return $preference === null || $preference->isForumReply();
    }

    public function canReceiveMarketingNotification(User $user): bool
    {
        $preference = $user->getNotificationPreference();

        return $preference !== null && $preference->isMarketing();
    }

    public function canReceiveActivityReminderNotification(User $user): bool
    {
        $preference = $user->getNotificationPreference();

        return $preference === null || $preference->isActivityReminder();
    }
}
