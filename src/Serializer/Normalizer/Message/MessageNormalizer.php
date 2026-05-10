<?php declare(strict_types=1);

namespace App\Serializer\Normalizer\Message;

use App\Entity\Message\Message;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Sanitizes the `content` field on Message entities embedded as nested objects in
 * other resource responses (e.g. MessageThreadMeta.last_message). Will be removed
 * once MessageThreadMeta is migrated to a DTO that composes MessageBuilder (#667).
 */
class MessageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'MESSAGE_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly HtmlSanitizerInterface $sanitizer
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<array-key, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null
     */
    public function normalize(mixed $message, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        $context[self::ALREADY_CALLED] = true;
        /** @var Message $message */
        $messageArray = $this->normalizer->normalize($message, $format, $context);
        if (is_array($messageArray)) {
            $messageArray['content'] = $this->sanitizer->sanitize(nl2br((string) $message->content));
        }

        return $messageArray;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Message;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Message::class => false];
    }
}
