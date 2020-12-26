<?php

namespace App\Serializer\Normalizer;

use App\Entity\Gallery;
use App\Serializer\GalleryImageSerializer;
use App\Serializer\User\UserArraySerializer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class GalleryNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public const CONTEXT_GALLERY = 'gallery';

    private ObjectNormalizer $normalizer;
    private GalleryImageSerializer $userGalleryImageSerializer;
    private UserArraySerializer $userArraySerializer;

    public function __construct(ObjectNormalizer $normalizer, GalleryImageSerializer $userGalleryImageSerializer, UserArraySerializer $userArraySerializer)
    {
        $this->normalizer = $normalizer;
        $this->userGalleryImageSerializer = $userGalleryImageSerializer;
        $this->userArraySerializer = $userArraySerializer;
    }

    public function normalize($object, string $format = null, array $context = array()): array
    {
        $data = $this->normalizer->normalize($object, $format, [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author', 'images', 'coverImage', 'viewCache']
        ]);

        $cover = $object->getCoverImage();
        $data['cover_image'] = $cover ? $this->userGalleryImageSerializer->toArray($cover) : null;
        $data['author'] = $object->getAuthor() ? $this->userArraySerializer->toArray($object->getAuthor()) : null;
        $data['image_count'] = count($object->getImages());

        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        $isContext = isset($context[self::CONTEXT_GALLERY]) ? $context[self::CONTEXT_GALLERY] : false;
        return $data instanceof Gallery && $isContext;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
