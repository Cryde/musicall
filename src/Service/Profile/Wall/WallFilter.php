<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

/** One of the wall's filters (#1317): « Posts », « Médias », « Annonces » or « Cours ». Without one, the wall is « Tout ». */
enum WallFilter: string
{
    case Post = 'post';
    case Media = 'media';
    case Announce = 'announce';
    case Course = 'course';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Whether the filter reads this source. « Cours » reads publications, keeping the courses only. */
    public function reads(WallItemKind $kind): bool
    {
        return match ($this) {
            self::Post => $kind === WallItemKind::Post,
            self::Media => $kind === WallItemKind::MusicianMedia || $kind === WallItemKind::TeacherMedia,
            self::Announce => $kind === WallItemKind::Announce,
            self::Course => $kind === WallItemKind::Publication,
        };
    }
}
