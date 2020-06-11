<?php

namespace App\Service\Builder\Wiki;

use App\Entity\Wiki\ArtistSocial;

class ArtistSocialDirector
{
    public function createFromArray(array $data): ArtistSocial
    {
        return (new ArtistSocial())
            ->setUrl($data['url'])
            ->setType($data['type']);
    }
}
