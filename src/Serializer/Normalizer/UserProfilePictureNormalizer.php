<?php

namespace App\Serializer\Normalizer;

use App\Entity\Image\UserProfilePicture;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserProfilePictureNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly UploaderHelper $uploaderHelper,
        private readonly CacheManager   $cacheManager
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        /** @var ?UserProfilePicture $object */
        if ($object) {
            $path = $this->uploaderHelper->asset($object, 'imageFile');

            return ['small' => $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small')];
        }

        return null;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UserProfilePicture;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            UserProfilePicture::class => false
        ];
    }
}