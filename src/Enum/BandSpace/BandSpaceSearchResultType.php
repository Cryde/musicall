<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * The kinds of record the command palette can return, in the order it groups them.
 *
 * Deliberately not BandSpaceModule: that enum describes modules, and the setlist module holds two
 * different kinds of record. A hit on a song and a hit on a setlist have to be told apart because
 * they open different things.
 */
enum BandSpaceSearchResultType: string
{
    case Agenda = 'agenda';
    case Task = 'task';
    case Note = 'note';
    case File = 'file';
    case Setlist = 'setlist';
    case Song = 'song';
    case Finance = 'finance';
}
