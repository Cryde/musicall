<?php declare(strict_types=1);

namespace App\Tests\Integration\Service\Finder\Musician;

use App\Service\Finder\Musician\Builder\AnnounceMusicianFilterBuilder;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The builder turns the model's answer into filters. Its job is to degrade, never to refuse: the announce
 * search filters on instrument and styles only when they are set, so an unresolved field must widen the
 * search rather than return null, which the interface renders as "no result".
 */
#[ResetDatabase]
class AnnounceMusicianFilterBuilderTest extends KernelTestCase
{
    private function builder(): AnnounceMusicianFilterBuilder
    {
        return static::getContainer()->get(AnnounceMusicianFilterBuilder::class);
    }

    /**
     * "Je cherche un groupe à joindre qui joue du métal à Pusignan" names no instrument. Requiring one
     * used to make this return null, so a perfectly clear search showed nothing.
     */
    public function test_a_search_naming_no_instrument_still_produces_filters(): void
    {
        $metal = StyleFactory::new()->asMetal()->create();

        $filter = $this->builder()->buildFromArray([
            'type' => '1',
            'instrument' => null,
            'styles' => [(string) $metal->id],
            'coordinates' => ['latitude' => 45.7955, 'longitude' => 5.0575],
        ]);

        $this->assertNotNull($filter);
        $this->assertSame(1, $filter->type);
        $this->assertNull($filter->instrument);
        $this->assertSame([(string) $metal->id], $filter->styles);
        $this->assertSame(45.7955, $filter->latitude);
    }

    public function test_an_absent_instrument_key_is_treated_as_no_instrument(): void
    {
        $filter = $this->builder()->buildFromArray(['type' => '1']);

        $this->assertNotNull($filter);
        $this->assertNull($filter->instrument);
        $this->assertSame([], $filter->styles);
        $this->assertNull($filter->latitude);
    }

    /**
     * The prompt hands the model a map of id => slug, so it sometimes answers with the slug. That is a
     * good answer and gets honoured rather than discarded.
     */
    public function test_a_slug_is_accepted_where_an_id_was_expected(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $metal = StyleFactory::new()->asMetal()->create();

        $filter = $this->builder()->buildFromArray([
            'type' => '2',
            'instrument' => $drum->slug,
            'styles' => [$metal->slug],
        ]);

        $this->assertNotNull($filter);
        $this->assertSame((string) $drum->id, $filter->instrument);
        $this->assertSame([(string) $metal->id], $filter->styles);
    }

    /**
     * The id columns are UUID typed, so handing Doctrine a plain word used to throw ValueNotConvertible
     * and surface as a 500 instead of a degraded search.
     */
    public function test_an_unresolvable_instrument_widens_the_search_instead_of_failing(): void
    {
        $metal = StyleFactory::new()->asMetal()->create();

        $filter = $this->builder()->buildFromArray([
            'type' => '1',
            'instrument' => 'un instrument qui n existe pas',
            'styles' => [(string) $metal->id, 'style-inconnu', ''],
        ]);

        $this->assertNotNull($filter);
        $this->assertNull($filter->instrument);
        // The one resolvable style survives, the rest are dropped.
        $this->assertSame([(string) $metal->id], $filter->styles);
    }

    public function test_duplicate_styles_are_collapsed(): void
    {
        $metal = StyleFactory::new()->asMetal()->create();

        $filter = $this->builder()->buildFromArray([
            'type' => '1',
            'styles' => [(string) $metal->id, $metal->slug],
        ]);

        $this->assertNotNull($filter);
        $this->assertSame([(string) $metal->id], $filter->styles);
    }

    public function test_a_missing_type_is_the_only_thing_that_yields_no_filter(): void
    {
        $this->assertNull($this->builder()->buildFromArray(['instrument' => null, 'styles' => []]));
    }
}
