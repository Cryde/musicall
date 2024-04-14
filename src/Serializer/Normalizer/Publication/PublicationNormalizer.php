<?php

namespace App\Serializer\Normalizer\Publication;

use App\Entity\Publication;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class PublicationNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    public function __construct(
        private readonly NormalizerInterface    $decorated,
        private readonly HtmlSanitizerInterface $appPublicationSanitizer
    ) {
    }

    public function normalize(mixed $object, string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        /** @var Publication $object */
        $normalizedData = $this->decorated->normalize($object, $format, $context);
        if (in_array(Publication::ITEM, $context['groups'] ?? [])) {
            $normalizedData['content'] = $this->appPublicationSanitizer->sanitize($object->getContent());
        }

        return $normalizedData;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Publication;
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Publication::class => false];
    }
}