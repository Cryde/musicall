<?php

declare(strict_types=1);

namespace App\Service\Musician\Match;

use App\Entity\Musician\MusicianAnnounce;

/** « Groupe cherche un batteur », « Guitariste cherche un groupe »: an announce the way the cards name it. */
final class AnnounceHeadline
{
    public static function of(MusicianAnnounce $announce): string
    {
        $musicianName = $announce->instrument->musicianName;

        return $announce->type === MusicianAnnounce::TYPE_MUSICIAN
            ? 'Groupe cherche un ' . mb_strtolower($musicianName)
            : $musicianName . ' cherche un groupe';
    }
}
