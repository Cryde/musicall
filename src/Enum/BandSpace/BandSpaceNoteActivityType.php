<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceNoteActivityType: string
{
    case Created = 'note_created';
    case Renamed = 'note_renamed';
    case EmojiChanged = 'note_emoji_changed';
    case ContentUpdated = 'note_content_updated';
    case Deleted = 'note_deleted';
    case NoteFileAttached = 'note_file_attached';

    /** The kind of item a line of this type names, which a screen can open (#1354); null when it names none. */
    public function resourceKind(): ?BandSpaceActivityResourceKind
    {
        return match ($this) {
            self::Created,
            self::Renamed,
            self::EmojiChanged,
            self::ContentUpdated,
            self::NoteFileAttached => BandSpaceActivityResourceKind::Note,
            self::Deleted => null,
        };
    }
}
