<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum BandSpaceModule: string
{
    case File = 'file';
    case Task = 'task';
    case Finance = 'finance';
    case Agenda = 'agenda';
    case Notes = 'notes';
    case Settings = 'settings';
    case Setlist = 'setlist';
}
