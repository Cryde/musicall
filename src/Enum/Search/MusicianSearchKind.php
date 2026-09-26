<?php

declare(strict_types=1);

namespace App\Enum\Search;

/** How a musician search was asked (#1075): through the filters, or in words for the AI to turn into filters. */
enum MusicianSearchKind: string
{
    case Filters = 'filters';
    case Ai = 'ai';
}
