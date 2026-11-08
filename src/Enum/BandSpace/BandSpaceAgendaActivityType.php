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
    case FeedGenerated = 'feed_generated';
    case FeedRevoked = 'feed_revoked';

    /** The kind of item a line of this type names, which a screen can open (#1354); null when it names none. */
    public function resourceKind(): ?BandSpaceActivityResourceKind
    {
        return match ($this) {
            self::EntryCreated,
            self::TitleChanged,
            self::DescriptionChanged,
            self::LocationChanged,
            self::EventDatetimeChanged,
            self::EndDatetimeChanged,
            self::IsAllDayChanged,
            self::OccurrenceCancelled,
            self::SeriesTruncated => BandSpaceActivityResourceKind::AgendaEntry,
            self::EntryDeleted, self::FeedGenerated, self::FeedRevoked => null,
        };
    }
}
