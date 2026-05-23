<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Setlist;

class SetlistItemSongInfo
{
    public string $id;
    public string $title;
    public ?int $tempo = null;
    public ?string $tonality = null;
    public ?int $referenceDuration = null;
    public ?string $archiveDatetime = null;
}
