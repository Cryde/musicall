<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Serializer\Normalizer\ReferenceNameNormalizer;
use App\Translation\ReferenceNameTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class ReferenceNameNormalizerTest extends TestCase
{
    public function test_an_instrument_gets_both_names_in_the_request_locale_in_place(): void
    {
        $normalized = $this->normalizer('en', ['@id' => '/api/instruments/1', 'name' => 'Guitare', 'musician_name' => 'Guitariste', 'slug' => 'guitare'])
            ->normalize(self::guitar(), 'jsonld');

        self::assertSame(['@id' => '/api/instruments/1', 'name' => 'Guitar', 'musician_name' => 'Guitarist', 'slug' => 'guitare'], $normalized);
    }

    public function test_a_style_keeps_its_database_name_in_french(): void
    {
        $style = new Style();
        $style->name = 'Rock';
        $style->slug = 'rock';

        $normalized = $this->normalizer('fr', ['@id' => '/api/styles/1', 'name' => 'Rock', 'slug' => 'rock'])->normalize($style, 'jsonld');

        self::assertSame(['@id' => '/api/styles/1', 'name' => 'Rock', 'slug' => 'rock'], $normalized);
    }

    public function test_a_name_the_regular_normalization_left_out_stays_out(): void
    {
        $normalized = $this->normalizer('en', ['@id' => '/api/instruments/1', 'name' => 'Guitare'])->normalize(self::guitar(), 'jsonld');

        self::assertSame(['@id' => '/api/instruments/1', 'name' => 'Guitar'], $normalized);
    }

    public function test_an_output_that_is_not_an_array_passes_through(): void
    {
        $normalized = $this->normalizer('en', '/api/instruments/1')->normalize(self::guitar(), 'jsonld');

        self::assertSame('/api/instruments/1', $normalized);
    }

    public function test_it_steps_aside_once_it_has_handed_the_object_on(): void
    {
        $normalizer = $this->normalizer('fr', []);

        self::assertTrue($normalizer->supportsNormalization(new Style()));
        self::assertFalse($normalizer->supportsNormalization(new Style(), null, ['reference_name_normalizer_already_called' => true]));
        self::assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @param array<string, string>|string $innerOutput what the regular normalizer would produce
     */
    private function normalizer(string $locale, array|string $innerOutput): ReferenceNameNormalizer
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['fr']);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'instrument.guitare' => 'Guitar',
            'instrument_musician.guitare' => 'Guitarist',
        ], 'en', 'reference+intl-icu');

        $normalizer = new ReferenceNameNormalizer(new ReferenceNameTranslator($translator));
        $normalizer->setNormalizer(new class($innerOutput) implements NormalizerInterface {
            /**
             * @param array<string, string>|string $output
             */
            public function __construct(private readonly array|string $output)
            {
            }

            /**
             * @param array<string, mixed> $context
             * @return array<string, string>|string
             */
            public function normalize(mixed $data, ?string $format = null, array $context = []): array|string
            {
                TestCase::assertTrue($context['reference_name_normalizer_already_called'] ?? false, 'the inner call must not loop back');

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

    private static function guitar(): Instrument
    {
        $instrument = new Instrument();
        $instrument->name = 'Guitare';
        $instrument->musicianName = 'Guitariste';
        $instrument->slug = 'guitare';

        return $instrument;
    }
}
