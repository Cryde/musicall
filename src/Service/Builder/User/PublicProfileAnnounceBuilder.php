<?php

declare(strict_types=1);

namespace App\Service\Builder\User;

use App\ApiResource\User\Profile\PublicProfileAnnounce;
use App\Entity\Musician\MusicianAnnounce;
use App\Translation\ReferenceNameTranslator;

/** An announce as a member's public pages show it: their profile and their wall. */
readonly class PublicProfileAnnounceBuilder
{
    public function __construct(private ReferenceNameTranslator $referenceNames)
    {
    }

    public function build(MusicianAnnounce $announce): PublicProfileAnnounce
    {
        $dto = new PublicProfileAnnounce();
        $dto->id = (string) $announce->id;
        $dto->creationDatetime = $announce->creationDatetime;
        $dto->type = $announce->type;
        $dto->instrumentName = $this->referenceNames->instrumentMusicianName($announce->instrument);
        $dto->locationName = $announce->locationName;
        $dto->styles = array_map(
            $this->referenceNames->style(...),
            $announce->styles->toArray()
        );

        return $dto;
    }
}
