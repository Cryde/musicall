<?php

namespace App\Serializer\Normalizer;

use App\Entity\Gallery;
use App\Serializer\GalleryImageSerializer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class UserGalleryNormalizer implements NormalizerInterface, NormalizerAwareInterface, CacheableSupportsMethodInterface
{
    use NormalizerAwareTrait;
    final public const CONTEXT_USER_GALLERY = 'user_gallery';

    private const ALREADY_CALLED = 'USER_GALLERY_NORMALIZER_ALREADY_CALLED';


    public function __construct(
        private readonly GalleryImageSerializer $userGalleryImageSerializer
    ) {
    }

    public function normalize($object, string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, array_merge([
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author', 'images', 'coverImage']
        ], $context));

        $cover = $object->getCoverImage();
        $data['coverImage'] = $cover ? $this->userGalleryImageSerializer->toArray($cover) : null;

        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        $isContext = $context[self::CONTEXT_USER_GALLERY] ?? false;
        return $data instanceof Gallery && $isContext;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
