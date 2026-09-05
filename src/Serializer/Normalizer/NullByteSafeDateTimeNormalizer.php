<?php declare(strict_types=1);

namespace App\Serializer\Normalizer;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Refuses a date carrying a null byte before Symfony's DateTimeNormalizer can choke on it.
 *
 * `DateTimeNormalizer` parses through `createFromFormat()`, because its own default format is
 * RFC3339 and that path is taken even when the property declares no format. `createFromFormat()`
 * raises a **ValueError** on a null byte rather than returning false, and the normalizer only
 * guards itself with `catch (\Exception)`. A `ValueError` extends `Error`, so it escapes and the
 * request ends as a 500 with a `request.CRITICAL` log line.
 *
 * One decorator covers every resource at once. Guarding per resource would not work anyway: on a
 * PATCH the input class is the resource itself, so every typed `DateTimeInterface` property is
 * denormalized from the body, including the read-only ones like `creationDatetime` that no
 * convention about writable fields would ever think to protect.
 *
 * The rejection is a `NotNormalizableValueException`, which is what the serializer already throws
 * for a date it cannot read, so this reuses the existing contract rather than inventing one:
 * `collect_denormalization_errors` is on globally, so it surfaces as a 422 naming the field.
 */
#[AsDecorator('serializer.normalizer.datetime')]
final class NullByteSafeDateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly NormalizerInterface&DenormalizerInterface $inner,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (is_string($data) && str_contains($data, "\0")) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                'The data contains a null byte.',
                $data,
                ['string'],
                $context['deserialization_path'] ?? null,
                true,
            );
        }

        return $this->inner->denormalize($data, $type, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsDenormalization($data, $type, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<array-key, mixed>|string|int|float|bool|\ArrayObject<array-key, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        return $this->inner->normalize($data, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    /**
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }
}
