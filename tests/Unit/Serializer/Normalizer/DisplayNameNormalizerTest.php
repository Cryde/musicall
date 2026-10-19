<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Serializer\Normalizer\DisplayNameNormalizer;
use App\Tests\Double\CatalogueTranslator;
use App\Translation\DisplayNameTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DisplayNameNormalizerTest extends TestCase
{
    public function test_a_closed_account_is_named_in_the_request_locale_in_place(): void
    {
        $normalized = $this->normalizer('en', ['id' => 'u1', 'display_name' => 'Utilisateur supprimé', 'username' => 'deleted_c7c9f2e1'])
            ->normalize(self::user(deleted: true), 'jsonld');

        self::assertSame(['id' => 'u1', 'display_name' => 'Deleted user', 'username' => 'deleted_c7c9f2e1'], $normalized);
    }

    public function test_french_reads_as_before(): void
    {
        $normalized = $this->normalizer('fr', ['id' => 'u1', 'display_name' => 'Utilisateur supprimé'])
            ->normalize(self::user(deleted: true), 'jsonld');

        self::assertSame(['id' => 'u1', 'display_name' => 'Utilisateur supprimé'], $normalized);
    }

    public function test_an_open_account_keeps_the_name_the_regular_normalization_wrote(): void
    {
        $normalized = $this->normalizer('en', ['id' => 'u1', 'display_name' => 'Alexandre Martin'])
            ->normalize(self::user(deleted: false), 'jsonld');

        self::assertSame(['id' => 'u1', 'display_name' => 'Alexandre Martin'], $normalized);
    }

    public function test_a_payload_without_the_name_stays_without_it(): void
    {
        $normalized = $this->normalizer('en', ['id' => 'u1'])->normalize(self::user(deleted: true), 'jsonld');

        self::assertSame(['id' => 'u1'], $normalized);
    }

    public function test_it_steps_aside_once_it_has_handed_the_object_on(): void
    {
        $normalizer = $this->normalizer('fr', []);

        self::assertTrue($normalizer->supportsNormalization(self::user(deleted: false)));
        self::assertFalse($normalizer->supportsNormalization(self::user(deleted: false), null, ['display_name_normalizer_already_called' => true]));
        self::assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @param array<string, string> $innerOutput what the regular normalizer would produce
     */
    private function normalizer(string $locale, array $innerOutput): DisplayNameNormalizer
    {
        $normalizer = new DisplayNameNormalizer(new DisplayNameTranslator(CatalogueTranslator::in($locale)));
        $normalizer->setNormalizer(new class($innerOutput) implements NormalizerInterface {
            /**
             * @param array<string, string> $output
             */
            public function __construct(private readonly array $output)
            {
            }

            /**
             * @param array<string, mixed> $context
             * @return array<string, string>
             */
            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                TestCase::assertTrue($context['display_name_normalizer_already_called'] ?? false, 'the inner call must not loop back');

                return $this->output;
            }

            /**
             * @param array<string, mixed> $context
             */
            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => false];
            }
        });

        return $normalizer;
    }

    private static function user(bool $deleted): User
    {
        $user = new User();
        $user->username = $deleted ? 'deleted_c7c9f2e1' : 'androidtest_123';
        $user->deletionDatetime = $deleted ? new \DateTimeImmutable('2026-09-01') : null;
        $user->profile = new UserProfile();

        return $user;
    }
}
