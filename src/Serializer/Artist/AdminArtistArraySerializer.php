<?php

namespace App\Serializer\Artist;

use App\Entity\Wiki\Artist;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class AdminArtistArraySerializer
{
    /**
     * @var AdminArtistSocialSerializer
     */
    private AdminArtistSocialSerializer $adminArtistSocialSerializer;
    /**
     * @var UploaderHelper
     */
    private UploaderHelper $uploaderHelper;
    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

    public function __construct(
        AdminArtistSocialSerializer $adminArtistSocialSerializer,
        UploaderHelper $uploaderHelper,
        CacheManager $cacheManager
    ) {
        $this->adminArtistSocialSerializer = $adminArtistSocialSerializer;
        $this->uploaderHelper = $uploaderHelper;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param Artist[] $artists
     *
     * @return array
     */
    public function listToArray($artists): array
    {
        $result = [];
        foreach ($artists as $artist) {
            $result[] = $this->toArray($artist);
        }

        return $result;
    }

    public function toArray(Artist $artist): array
    {
        $imagePath = $artist->getCover() ? $this->uploaderHelper->asset($artist->getCover(), 'imageFile') : '';

        return [
            'id'         => $artist->getId(),
            'name'       => $artist->getName(),
            'biography'  => $artist->getBiography(),
            'label_name' => $artist->getLabelName(),
            'members'    => $artist->getMembers(),
            'socials'    => $this->adminArtistSocialSerializer->listToArray($artist->getSocials()),
            'cover'      => $imagePath ? $this->cacheManager->generateUrl($imagePath, 'wiki_artist_cover_filter') : '',
        ];
    }
}
