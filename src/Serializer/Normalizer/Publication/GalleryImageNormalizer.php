<?php declare(strict_types=1);

namespace App\Serializer\Normalizer\Publication;

use App\Entity\Image\GalleryImage;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class GalleryImageNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly UploaderHelper $uploaderHelper,
        private readonly CacheManager   $cacheManager
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        /** @var ?GalleryImage $object */
        if ($object) {
            $path = $this->uploaderHelper->asset($object, 'imageFile');

            return $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium');
        }

        return null;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof GalleryImage;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [GalleryImage::class => false];
    }
}
