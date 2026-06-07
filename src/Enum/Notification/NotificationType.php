<?php

declare(strict_types=1);

namespace App\Enum\Notification;

enum NotificationType: string
{
    case BandSpaceInvitation = 'band_space_invitation';
    case ForumTopicReply = 'forum_topic_reply';
    case PublicationComment = 'publication_comment';
    case CommentReply = 'comment_reply';
    case TaskMention = 'task_mention';
    case BandSpaceTaskAssignment = 'band_space_task_assignment';
    case PublicationApproved = 'publication_approved';
    case PublicationRejected = 'publication_rejected';
    case GalleryApproved = 'gallery_approved';
    case GalleryRejected = 'gallery_rejected';
    case BandSpaceRoleChanged = 'band_space_role_changed';
    case BandSpaceMemberRemoved = 'band_space_member_removed';
    case BandSpaceAgendaEntryCreated = 'band_space_agenda_entry_created';
}
