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
    public const CONTEXT_USER_GALLERY = 'user_gallery';

    private $normalizer;
    /**
     * @var GalleryImageSerializer
     */
    private GalleryImageSerializer $userGalleryImageSerializer;

    public function __construct(ObjectNormalizer $normalizer, GalleryImageSerializer $userGalleryImageSerializer)
    {
        $this->normalizer = $normalizer;
        $this->userGalleryImageSerializer = $userGalleryImageSerializer;
    }

    public function normalize($object, string $format = null, array $context = array()): array
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
        $isContext = isset($context[self::CONTEXT_USER_GALLERY]) ? $context[self::CONTEXT_USER_GALLERY] : false;
        return $data instanceof Gallery && $isContext;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
