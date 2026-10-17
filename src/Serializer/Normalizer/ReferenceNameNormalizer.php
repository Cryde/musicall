<?php

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Translation\ReferenceNameTranslator;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The instrument and style lists are the entities themselves, with no builder to translate them in, so
 * their names are swapped for the request locale's after the regular normalization.
 */
final class ReferenceNameNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'reference_name_normalizer_already_called';

    public function __construct(
        private readonly ReferenceNameTranslator $referenceNames,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<array-key, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->normalizer->normalize($data, $format, [self::ALREADY_CALLED => true] + $context);
        if (!is_array($normalized)) {
            return $normalized;
        }

        $names = match (true) {
            $data instanceof Instrument => [
                'name' => $this->referenceNames->instrument($data),
                'musician_name' => $this->referenceNames->instrumentMusicianName($data),
            ],
            $data instanceof Style => ['name' => $this->referenceNames->style($data)],
            default => [],
        };

        // Only the keys the regular normalization wrote, assigned in place so they keep their order.
        foreach (array_intersect_key($names, $normalized) as $key => $name) {
            $normalized[$key] = $name;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return ($data instanceof Instrument || $data instanceof Style) && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable: whether it applies depends on the context flag.
        return [Instrument::class => false, Style::class => false];
    }
}
