<?php

declare(strict_types=1);

namespace App\Enum\Notification;

enum NotificationType: string
{
    case BandSpaceInvitation = 'band_space_invitation';
    case BandSpaceInvitationAccepted = 'band_space_invitation_accepted';
    case BandSpaceInvitationDeclined = 'band_space_invitation_declined';
    case ForumTopicReply = 'forum_topic_reply';
    case PublicationComment = 'publication_comment';
    case CommentReply = 'comment_reply';
    case TaskMention = 'task_mention';
    case BandSpaceChatMention = 'band_space_chat_mention';
    case TaskComment = 'task_comment';
    case BandSpaceTaskAssignment = 'band_space_task_assignment';
    case PublicationApproved = 'publication_approved';
    case PublicationRejected = 'publication_rejected';
    case GalleryApproved = 'gallery_approved';
    case GalleryRejected = 'gallery_rejected';
    case BandSpaceRoleChanged = 'band_space_role_changed';
    case BandSpaceMemberRemoved = 'band_space_member_removed';
    case BandSpaceMemberLeft = 'band_space_member_left';
    case BandSpaceAgendaEntryCreated = 'band_space_agenda_entry_created';
    case BandSpaceFinanceSplitAssigned = 'band_space_finance_split_assigned';
    case BandSpaceDeletionScheduled = 'band_space_deletion_scheduled';
    case BandSpaceDeletionCancelled = 'band_space_deletion_cancelled';
    case ReportReceived = 'report_received';
    case ReportResolved = 'report_resolved';

    /**
     * Where a push of this type sits (#1110), or null when it stays in the bell. A match on purpose: a
     * new type fails static analysis until somebody decides.
     */
    public function pushCategory(): ?PushCategory
    {
        return match ($this) {
            self::ForumTopicReply => PushCategory::ForumReply,
            self::PublicationComment, self::CommentReply => PushCategory::PublicationComment,
            self::PublicationApproved, self::PublicationRejected,
            self::GalleryApproved, self::GalleryRejected => PushCategory::Moderation,
            self::BandSpaceChatMention, self::TaskMention => PushCategory::BandMention,
            self::BandSpaceTaskAssignment, self::TaskComment => PushCategory::BandTasks,
            self::BandSpaceAgendaEntryCreated => PushCategory::BandAgenda,
            self::BandSpaceFinanceSplitAssigned => PushCategory::BandFinance,
            self::BandSpaceInvitation, self::BandSpaceInvitationAccepted, self::BandSpaceInvitationDeclined,
            self::BandSpaceRoleChanged, self::BandSpaceMemberRemoved, self::BandSpaceMemberLeft => PushCategory::BandMembership,
            self::BandSpaceDeletionScheduled, self::BandSpaceDeletionCancelled => PushCategory::Always,
            // A report and its decision are not urgent; the bell is enough.
            self::ReportReceived, self::ReportResolved => null,
        };
    }
}
