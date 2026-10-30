<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist\Lyrics;

/** The song some lyrics are linked to. Its key is the one a transposition moves. */
class LyricsSongInfo
{
    public string $id;
    public string $title;
    public ?string $tonality = null;
    public ?\DateTimeInterface $archiveDatetime = null;
}
