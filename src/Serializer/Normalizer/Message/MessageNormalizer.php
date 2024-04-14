<?php

namespace App\Serializer\Normalizer\Message;

use App\Entity\Message\Message;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MessageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'MESSAGE_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly HtmlSanitizerInterface $sanitizer
    ) {
    }

    public function normalize(mixed $message, string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        $context[self::ALREADY_CALLED] = true;
        /** @var Message $message */
        $messageArray = $this->normalizer->normalize($message, $format, $context);
        $messageArray['content'] = $this->sanitizer->sanitize(nl2br($message->getContent()));

        return $messageArray;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Message;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Message::class => true];
    }
}