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
    case TaskDeleted = 'task_deleted';
    case TasksDeleted = 'tasks_deleted';
    case CommentAdded = 'comment_added';
    case CommentEdited = 'comment_edited';
    case CommentDeleted = 'comment_deleted';
    case Mention = 'mention';

    /** The kind of item a line of this type names, which a screen can open (#1354); null when it names none. */
    public function resourceKind(): ?BandSpaceActivityResourceKind
    {
        return match ($this) {
            self::StatusChanged,
            self::DueDateChanged,
            self::CategoryChanged,
            self::AssigneeAdded,
            self::AssigneeRemoved,
            self::TaskArchived,
            self::TaskUnarchived,
            self::CommentAdded,
            self::CommentEdited,
            self::CommentDeleted,
            self::Mention => BandSpaceActivityResourceKind::Task,
            self::TaskDeleted, self::TasksDeleted => null,
        };
    }
}
