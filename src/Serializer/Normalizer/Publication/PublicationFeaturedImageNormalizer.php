<?php

namespace App\Serializer\Normalizer\Publication;

use App\Entity\Image\PublicationFeaturedImage;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationFeaturedImageNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(
        private readonly UploaderHelper $uploaderHelper,
        private readonly CacheManager   $cacheManager
    ) {
    }

    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        /** @var ?PublicationFeaturedImage $object */
        if ($object) {
            $path = $this->uploaderHelper->asset($object, 'imageFile');

            return $this->cacheManager->getBrowserPath($path, 'featured_cover_filter');
        }

        return null;
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof PublicationFeaturedImage;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}