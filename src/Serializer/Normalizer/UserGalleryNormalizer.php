<?php

namespace App\Serializer\Normalizer;

use App\Entity\Gallery;
use App\Serializer\GalleryImageSerializer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class UserGalleryNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    final public const CONTEXT_USER_GALLERY = 'user_gallery';

    public function __construct(
        private readonly ObjectNormalizer       $normalizer,
        private readonly GalleryImageSerializer $userGalleryImageSerializer
    ) {
    }

    public function normalize($object, string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author', 'images', 'coverImage']
        ]);

        $cover = $object->getCoverImage();
        $data['coverImage'] = $cover ? $this->userGalleryImageSerializer->toArray($cover) : null;

        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        $isContext = $context[self::CONTEXT_USER_GALLERY] ?? false;
        return $data instanceof Gallery && $isContext;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
