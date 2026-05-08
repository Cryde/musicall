<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceTaskActivityType: string
{
    case StatusChanged = 'status_changed';
    case DueDateChanged = 'due_date_changed';
    case CategoryChanged = 'category_changed';
    case AssigneeAdded = 'assignee_added';
    case AssigneeRemoved = 'assignee_removed';
    case TaskArchived = 'task_archived';
    case TaskUnarchived = 'task_unarchived';
    case CommentAdded = 'comment_added';
    case CommentEdited = 'comment_edited';
    case CommentDeleted = 'comment_deleted';
    case Mention = 'mention';
}
