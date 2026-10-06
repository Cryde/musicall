<?php declare(strict_types=1);

namespace App\Enum\Notification;

use App\Entity\User\UserNotificationPreference;

/**
 * The switch a push sits behind on the phone (#1110). Separate from the email preferences, which
 * cover none of the Band Space events most pushes carry.
 */
enum PushCategory: string
{
    case MessageReceived = 'message_received';
    case PublicationComment = 'publication_comment';
    case ForumReply = 'forum_reply';
    case Moderation = 'moderation';
    case BandChat = 'band_chat';
    case BandMention = 'band_mention';
    case BandTasks = 'band_tasks';
    case BandAgenda = 'band_agenda';
    case BandFinance = 'band_finance';
    case BandMembership = 'band_membership';
    /** Behind no switch: a user cannot undo a band deletion they never heard about. */
    case Always = 'always';

    public function isEnabledIn(UserNotificationPreference $preference): bool
    {
        return match ($this) {
            self::MessageReceived => $preference->pushMessageReceived,
            self::PublicationComment => $preference->pushPublicationComment,
            self::ForumReply => $preference->pushForumReply,
            self::Moderation => $preference->pushModeration,
            self::BandChat => $preference->pushBandChat,
            self::BandMention => $preference->pushBandMention,
            self::BandTasks => $preference->pushBandTasks,
            self::BandAgenda => $preference->pushBandAgenda,
            self::BandFinance => $preference->pushBandFinance,
            self::BandMembership => $preference->pushBandMembership,
            self::Always => true,
        };
    }
}
