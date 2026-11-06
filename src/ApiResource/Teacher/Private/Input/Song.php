<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** One of the « Morceaux travaillés » (#1312). The artist may be left out, the title may not. */
class Song
{
    #[Assert\Length(max: 100, maxMessage: 'profile.teacher.song.artist_too_long')]
    public ?string $artist = null;

    #[Assert\NotBlank(message: 'profile.teacher.song.title_required')]
    #[Assert\Length(max: 100, maxMessage: 'profile.teacher.song.title_too_long')]
    public ?string $title = null;
}
