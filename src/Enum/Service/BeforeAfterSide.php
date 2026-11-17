<?php

declare(strict_types=1);

namespace App\Enum\Service;

/** One half of « Avant, après » (#1410): the raw take, then the engineer's work on it. */
enum BeforeAfterSide: string
{
    case Before = 'before';
    case After = 'after';
}
