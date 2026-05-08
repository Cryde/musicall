<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceNoteActivityType: string
{
    case Created = 'note_created';
    case Renamed = 'note_renamed';
    case EmojiChanged = 'note_emoji_changed';
    case ContentUpdated = 'note_content_updated';
    case Deleted = 'note_deleted';
}
