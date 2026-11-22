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
    /** One track, as « Mixage d'un titre » (#1410). */
    case Track = 'track';
    case Hour = 'hour';
    case Day = 'day';
    /** One concert, shoot or session, as « Captation d'un concert » (#1411). */
    case Event = 'event';
    /** A price for the whole job, as « EP de 5 titres ». */
    case Flat = 'flat';
}
