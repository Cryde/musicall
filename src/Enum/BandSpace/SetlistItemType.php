<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum SetlistItemType: string
{
    case Song = 'song';
    case Interlude = 'interlude';
    case Break = 'break';
    case Talk = 'talk';
}
