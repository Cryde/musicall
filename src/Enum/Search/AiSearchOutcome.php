<?php

declare(strict_types=1);

namespace App\Enum\Search;

/** What the AI made of a search typed in words (#1075). */
enum AiSearchOutcome: string
{
    /** It produced filters, which the page then searched with. */
    case Filters = 'filters';
    /** It understood nothing it could search with. */
    case Nothing = 'nothing';
    /** It answered with something unusable. */
    case Failed = 'failed';
}
