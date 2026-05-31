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
}
