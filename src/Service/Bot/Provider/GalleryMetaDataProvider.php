<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Service\Bot\BotMetaDataProviderInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class GalleryMetaDataProvider implements BotMetaDataProviderInterface
{
    public function __construct(
        private GalleryRepository $galleryRepository,
        private UploaderHelper    $uploaderHelper,
        private CacheManager      $cacheManager,
    ) {
    }

    public function supports(string $uri): bool
    {
        return str_starts_with($uri, '/photos/');
    }

    public function getMetaData(string $uri): array
    {
        if (!preg_match('#^/photos/(.+)$#', $uri, $matches)) {
            return [];
        }

        $gallery = $this->galleryRepository->findOneBy([
            'slug' => $matches[1],
            'status' => Gallery::STATUS_ONLINE,
        ]);

        if (!$gallery) {
            return [];
        }

        $cover = null;
        if ($gallery->getCoverImage()) {
            $path = $this->uploaderHelper->asset($gallery->getCoverImage(), 'imageFile');
            $cover = $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_full');
        }

        return [
            'title' => $gallery->getTitle(),
            'description' => $gallery->getDescription(),
            'cover' => $cover,
        ];
    }
}
