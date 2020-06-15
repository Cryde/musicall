<?php

namespace App\Service\Builder\Wiki;

use App\Entity\Wiki\Artist;

class ArtistDirector
{
    /**
     * @var ArtistSocialDirector
     */
    private ArtistSocialDirector $artistSocialDirector;

    public function __construct(ArtistSocialDirector $artistSocialDirector)
    {
        $this->artistSocialDirector = $artistSocialDirector;
    }

    public function createFromArray(array $data)
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
