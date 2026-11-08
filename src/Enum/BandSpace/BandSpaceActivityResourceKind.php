<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * What an activity line's `resource_id` names when a screen can open it (#1354). Each activity type
 * enum declares its own in `resourceKind()`; tests/Fixtures/BandSpaceActivity/links.json holds them to
 * the type table of `assets/js/utils/bandSpaceActivityLink.js`.
 */
enum BandSpaceActivityResourceKind: string
{
    case Task = 'task';
    case Note = 'note';
    case AgendaEntry = 'agenda_entry';
    case FinanceEntry = 'finance_entry';
    case File = 'file';
    case Setlist = 'setlist';
    case Song = 'song';
    case Lyrics = 'lyrics';
    case Rider = 'rider';
}
