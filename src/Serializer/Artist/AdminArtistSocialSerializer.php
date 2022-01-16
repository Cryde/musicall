<?php

namespace App\Serializer\Artist;

use App\Entity\Wiki\ArtistSocial;

class AdminArtistSocialSerializer
{
    /**
     * @param ArtistSocial[] $artistSocials
     */
    public function listToArray(array $artistSocials): array
    {
        $result = [];
        foreach ($artistSocials as $artistSocial) {
            $result[] = $this->toArray($artistSocial);
        }

        return $result;
    }

    public function toArray(ArtistSocial $artistSocial): array
    {
        return [
            'type' => $artistSocial->getType(),
            'url'  => $artistSocial->getUrl(),
        ];
    }
}
