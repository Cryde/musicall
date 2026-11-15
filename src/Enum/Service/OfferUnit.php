<?php

declare(strict_types=1);

namespace App\Enum\Service;

/** What a service offer's price buys, its quantity counting that unit. */
enum OfferUnit: string
{
    /** One lesson, the quantity being its length in minutes. */
    case Lesson = 'lesson';
    /** A package of lessons, the quantity being how many (null when the teacher did not say). */
    case Package = 'package';
}
