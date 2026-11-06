<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Musician\MusicianProfileMedia;
use App\Entity\Profile\ProfilePost;
use App\Entity\Publication;
use App\Entity\Teacher\TeacherProfileMedia;

/** One row of a wall, whatever table it comes from, with its place in the merged order. */
final readonly class WallEntry
{
    public WallCursor $cursor;

    public function __construct(
        public WallItemKind $kind,
        public \DateTimeInterface $date,
        public ProfilePost|MusicianAnnounce|Publication|MusicianProfileMedia|TeacherProfileMedia $row,
    ) {
        $this->cursor = WallCursor::at($kind, $date, (string) $row->id);
    }
}
