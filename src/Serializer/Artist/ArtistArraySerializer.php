<?php

namespace App\Serializer\Artist;

use App\Entity\Wiki\Artist;
use App\Service\Formatter\Artist\ArtistTextFormatter;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class ArtistArraySerializer
{
    /**
     * @var UploaderHelper
     */
    private UploaderHelper $uploaderHelper;
    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;
    /**
     * @var ArtistSocialSerializer
     */
    private ArtistSocialSerializer $artistSocialSerializer;
    /**
     * @var ArtistTextFormatter
     */
    private ArtistTextFormatter $artistTextFormatter;

    public function __construct(
        ArtistSocialSerializer $artistSocialSerializer,
        UploaderHelper $uploaderHelper,
        CacheManager $cacheManager,
        ArtistTextFormatter $artistTextFormatter
    ) {
        $this->uploaderHelper = $uploaderHelper;
        $this->cacheManager = $cacheManager;
        $this->artistSocialSerializer = $artistSocialSerializer;
        $this->artistTextFormatter = $artistTextFormatter;
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
            'slug'       => $artist->getSlug(),
            'biography'  => $this->artistTextFormatter->formatNewLine($artist->getBiography() ?? ''),
            'label_name' => $artist->getLabelName(),
            'members'    => $this->artistTextFormatter->formatNewLine($artist->getMembers() ?? ''),
            'socials'    => $this->artistSocialSerializer->listToArray($artist->getSocials()),
            'cover'      => $imagePath ? $this->cacheManager->generateUrl($imagePath, 'wiki_artist_cover_filter') : '',
        ];
    }
}
