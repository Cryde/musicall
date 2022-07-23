<?php

namespace App\Service\Builder\Wiki;

use App\Entity\Wiki\Artist;

class ArtistDirector
{
    public function __construct(private readonly ArtistSocialDirector $artistSocialDirector)
    {
    }

    public function createFromArray(array $data): Artist
    {
        $artist = (new Artist())
            ->setBiography($data['biography'] ?? '')
            ->setMembers($data['members'] ?? '')
            ->setLabelName($data['label_name'] ?? '')
            ->setCountryCode($data['country_code'] ?? '')
        ;

        foreach ($data['socials'] ?? [] as $social) {
            $artist->addSocial($this->artistSocialDirector->createFromArray($social));
        }

        return $artist;
    }
}
