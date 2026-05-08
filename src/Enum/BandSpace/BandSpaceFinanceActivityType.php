<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceFinanceActivityType: string
{
    case EntryCreated = 'entry_created';
    case EntryStatusChanged = 'entry_status_changed';
    case EntryLabelChanged = 'entry_label_changed';
    case EntryAmountChanged = 'entry_amount_changed';
    case EntryCategoryChanged = 'entry_category_changed';
    case EntryDateChanged = 'entry_date_changed';
    case EntryDeleted = 'entry_deleted';
    case SplitAdded = 'split_added';
    case SplitRemoved = 'split_removed';
    case CategoryCreated = 'category_created';
    case CategoryRenamed = 'category_renamed';
    case CategoryDeleted = 'category_deleted';
    case RecurrenceCreated = 'recurrence_created';
    case RecurrenceUpdated = 'recurrence_updated';
    case RecurrenceStarted = 'recurrence_started';
    case RecurrenceStopped = 'recurrence_stopped';
    case RecurrenceEndDateChanged = 'recurrence_end_date_changed';
    case RecurrenceDeleted = 'recurrence_deleted';
    case CategoriesBootstrapped = 'categories_bootstrapped';
}
