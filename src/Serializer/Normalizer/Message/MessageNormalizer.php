<?php

namespace App\Serializer\Normalizer\Message;

use App\Entity\Message\Message;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class MessageNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(
        private readonly ObjectNormalizer       $normalizer,
        private readonly HtmlSanitizerInterface $sanitizer
    ) {
    }

    public function normalize(mixed $message, string $format = null, array $context = [])
    {
        /** @var Message $message */
        $messageArray = $this->normalizer->normalize($message, $format, $context);
        $messageArray['content'] = $this->sanitizer->sanitize(nl2br($message->getContent()));

        return $messageArray;
    }

    public function supportsNormalization(mixed $data, string $format = null): bool
    {
        return $data instanceof Message;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}