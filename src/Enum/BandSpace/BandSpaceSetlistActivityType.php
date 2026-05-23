<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceSetlistActivityType: string
{
    // Song catalog
    case SongAdded = 'song_added';
    case SongUpdated = 'song_updated';
    case SongArchived = 'song_archived';
    case SongUnarchived = 'song_unarchived';
    case SongFileAttached = 'song_file_attached';
    case SongFileDetached = 'song_file_detached';

    // Setlist
    case SetlistCreated = 'setlist_created';
    case SetlistRenamed = 'setlist_renamed';
    case SetlistDuplicated = 'setlist_duplicated';
    case SetlistArchived = 'setlist_archived';
    case SetlistUnarchived = 'setlist_unarchived';

    // Setlist items
    case SetlistItemAdded = 'setlist_item_added';
    case SetlistItemRemoved = 'setlist_item_removed';
    case SetlistItemReordered = 'setlist_item_reordered';
    case SetlistItemUpdated = 'setlist_item_updated';

    // Setlist files
    case SetlistFileAttached = 'setlist_file_attached';
    case SetlistFileDetached = 'setlist_file_detached';
}
