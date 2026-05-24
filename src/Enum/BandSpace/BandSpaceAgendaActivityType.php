<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceAgendaActivityType: string
{
    case EntryCreated = 'entry_created';
    case EntryDeleted = 'entry_deleted';
    case TitleChanged = 'title_changed';
    case DescriptionChanged = 'description_changed';
    case LocationChanged = 'location_changed';
    case EventDatetimeChanged = 'event_datetime_changed';
    case EndDatetimeChanged = 'end_datetime_changed';
    case IsAllDayChanged = 'is_all_day_changed';
    case OccurrenceCancelled = 'occurrence_cancelled';
    case SeriesTruncated = 'series_truncated';
}
