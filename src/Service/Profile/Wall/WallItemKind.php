<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

/**
 * What a wall line is read from (#1316). The rank breaks a tie between two kinds dated the same second, so
 * the order is total and a cursor always knows which side of it a row falls.
 */
enum WallItemKind: string
{
    case Post = 'post';
    case Announce = 'announce';
    case Publication = 'publication';
    case MusicianMedia = 'musician_media';
    case TeacherMedia = 'teacher_media';

    public function rank(): int
    {
        return match ($this) {
            self::Post => 5,
            self::Announce => 4,
            self::Publication => 3,
            self::MusicianMedia => 2,
            self::TeacherMedia => 1,
        };
    }
}
