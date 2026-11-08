<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceSetlistActivityType: string
{
    // Song catalog
    case SongAdded = 'song_added';
    case SongUpdated = 'song_updated';
    case SongArchived = 'song_archived';
    /** Several at once from the repertoire's selection (#1063). */
    case SongsArchived = 'songs_archived';
    case SongUnarchived = 'song_unarchived';
    case SongFileAttached = 'song_file_attached';
    case SongFileDetached = 'song_file_detached';

    // Lyrics, written apart from a song and linked to one later (#1260)
    case LyricsAdded = 'lyrics_added';
    case LyricsUpdated = 'lyrics_updated';
    case LyricsArchived = 'lyrics_archived';
    case LyricsRestored = 'lyrics_restored';
    case LyricsLinked = 'lyrics_linked';
    case LyricsUnlinked = 'lyrics_unlinked';

    // Setlist
    case SetlistCreated = 'setlist_created';
    case SetlistRenamed = 'setlist_renamed';
    case SetlistDuplicated = 'setlist_duplicated';
    case SetlistArchived = 'setlist_archived';
    case SetlistUnarchived = 'setlist_unarchived';

    // Setlist items
    case SetlistItemAdded = 'setlist_item_added';
    /** Several songs at once, « Ajouter tout le répertoire » (#1062). */
    case SetlistItemsAdded = 'setlist_items_added';
    /** The running order of another setlist copied in (#1062). */
    case SetlistItemsCopied = 'setlist_items_copied';
    case SetlistItemRemoved = 'setlist_item_removed';
    case SetlistItemReordered = 'setlist_item_reordered';
    case SetlistItemUpdated = 'setlist_item_updated';

    // Setlist files
    case SetlistFileAttached = 'setlist_file_attached';
    case SetlistFileDetached = 'setlist_file_detached';

    /** The kind of item a line of this type names, which a screen can open (#1354); null when it names none. */
    public function resourceKind(): ?BandSpaceActivityResourceKind
    {
        return match ($this) {
            self::SetlistCreated,
            self::SetlistRenamed,
            self::SetlistDuplicated,
            self::SetlistUnarchived,
            self::SetlistItemAdded,
            self::SetlistItemsAdded,
            self::SetlistItemsCopied,
            self::SetlistItemRemoved,
            self::SetlistItemReordered,
            self::SetlistItemUpdated,
            self::SetlistFileAttached,
            self::SetlistFileDetached => BandSpaceActivityResourceKind::Setlist,
            self::SongAdded,
            self::SongUpdated,
            self::SongUnarchived,
            self::SongFileAttached,
            self::SongFileDetached => BandSpaceActivityResourceKind::Song,
            self::LyricsAdded,
            self::LyricsUpdated,
            self::LyricsRestored,
            self::LyricsLinked,
            self::LyricsUnlinked => BandSpaceActivityResourceKind::Lyrics,
            self::SongArchived, self::SongsArchived, self::LyricsArchived, self::SetlistArchived => null,
        };
    }
}
