<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\User;
use App\Translation\DisplayNameTranslator;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A payload that embeds the User entity (direct messages, their threads) has no builder to name a closed
 * account in, so its `display_name` is swapped for the request locale's after the regular normalization.
 */
final class DisplayNameNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'display_name_normalizer_already_called';
    private const string DISPLAY_NAME = 'display_name';

    public function __construct(
        private readonly DisplayNameTranslator $displayNames,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<array-key, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->normalizer->normalize($data, $format, [self::ALREADY_CALLED => true] + $context);

        // Only a key the regular normalization wrote, assigned in place so it keeps its order.
        if ($data instanceof User && $data->isDeleted() && is_array($normalized) && array_key_exists(self::DISPLAY_NAME, $normalized)) {
            $normalized[self::DISPLAY_NAME] = $this->displayNames->deletedUser();
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof User && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable: whether it applies depends on the context flag.
        return [User::class => false];
    }
}
