<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\File;

use App\Service\BandSpace\File\BandSpaceFileAttachmentLabels;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use App\Tests\Double\CatalogueTranslator;
use PHPUnit\Framework\TestCase;

/**
 * The sentence every delete path refuses with. The paths naming the same sources in the same words is
 * the whole point of the class, so the wording is pinned here rather than only through the handful of
 * source types the API tests happen to attach.
 */
class BandSpaceFileAttachmentLabelsTest extends TestCase
{
    public function test_an_empty_list_still_names_something(): void
    {
        $this->assertSame('une autre ressource', $this->labels('fr')->describe([]));
        $this->assertSame('another resource', $this->labels('en')->describe([]));
    }

    /**
     * The five types of BandSpaceFileSourceTypes::ALL, one by one. song and setlist used to fall
     * through to the unknown fallback, which is what this class was extracted to fix.
     */
    public function test_every_source_type_has_its_own_french_noun(): void
    {
        $labels = $this->labels('fr');

        $this->assertSame('une tâche', $labels->describe(['task']));
        $this->assertSame('une entrée financière', $labels->describe(['finance']));
        $this->assertSame('une note', $labels->describe(['note']));
        $this->assertSame('une chanson', $labels->describe(['song']));
        $this->assertSame('une setlist', $labels->describe(['setlist']));
    }

    public function test_every_source_type_has_its_own_english_noun(): void
    {
        $labels = $this->labels('en');

        $this->assertSame('a task', $labels->describe(['task']));
        $this->assertSame('a finance entry', $labels->describe(['finance']));
        $this->assertSame('a note', $labels->describe(['note']));
        $this->assertSame('a song', $labels->describe(['song']));
        $this->assertSame('a setlist', $labels->describe(['setlist']));
    }

    /**
     * Guards the pair against drift: a sixth source type added to the allowlist without a noun here
     * would silently be described as "une autre ressource" in the refusal.
     */
    public function test_no_allowed_source_type_falls_back_to_the_unknown_label(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $labels = $this->labels($locale);
            $unknown = $labels->describe(['gigposter']);
            foreach (BandSpaceFileSourceTypes::ALL as $sourceType) {
                $this->assertNotSame(
                    $unknown,
                    $labels->describe([$sourceType]),
                    sprintf('Source type "%s" has no %s noun', $sourceType, $locale),
                );
            }
        }
    }

    public function test_an_unknown_source_type_falls_back(): void
    {
        $this->assertSame('une autre ressource', $this->labels('fr')->describe(['gigposter']));
        $this->assertSame('another resource', $this->labels('en')->describe(['gigposter']));
    }

    public function test_two_types_are_joined_with_et_in_french_and_with_and_in_english(): void
    {
        $this->assertSame('une note et une tâche', $this->labels('fr')->describe(['note', 'task']));
        $this->assertSame('a note and a task', $this->labels('en')->describe(['note', 'task']));
    }

    /**
     * Worded like the front end's Intl.ListFormat, so the bulk delete bar and the refusal agree: French
     * has no comma before « et », English has one before "and".
     */
    public function test_three_or_more_types_are_a_comma_list_ending_in_the_joiner(): void
    {
        $french = $this->labels('fr');
        $english = $this->labels('en');

        $this->assertSame('une note, une chanson et une tâche', $french->describe(['note', 'song', 'task']));
        $this->assertSame(
            'une note, une setlist, une chanson et une tâche',
            $french->describe(['note', 'setlist', 'song', 'task']),
        );
        $this->assertSame('a note, a song, and a task', $english->describe(['note', 'song', 'task']));
        $this->assertSame(
            'a note, a setlist, a song, and a task',
            $english->describe(['note', 'setlist', 'song', 'task']),
        );
    }

    /**
     * The single file paths hand over the types in whatever order the database returned the rows in,
     * so the sentence must not depend on it.
     */
    public function test_types_are_named_in_a_stable_order_whatever_the_input_order(): void
    {
        $labels = $this->labels('fr');

        $this->assertSame('une note et une tâche', $labels->describe(['task', 'note']));
        $this->assertSame(
            'une entrée financière, une note et une tâche',
            $labels->describe(['task', 'finance', 'note']),
        );
        $this->assertSame(
            'a finance entry, a note, and a task',
            $this->labels('en')->describe(['note', 'task', 'finance']),
        );
    }

    /**
     * The caller passes one row per attachment, so the same type arrives many times over: three files
     * attached to three tasks must read "une tâche", not "une tâche, une tâche et une tâche".
     */
    public function test_repeated_types_are_named_once(): void
    {
        $labels = $this->labels('fr');

        $this->assertSame('une tâche', $labels->describe(['task', 'task', 'task']));
        $this->assertSame('une note et une tâche', $labels->describe(['task', 'note', 'task', 'note']));
    }

    /**
     * Two unknown types are one "une autre ressource", not two, so the list never repeats itself.
     */
    public function test_several_unknown_types_collapse_into_one_fallback(): void
    {
        $this->assertSame(
            'une autre ressource et une tâche',
            $this->labels('fr')->describe(['task', 'gigposter', 'poster']),
        );
        $this->assertSame(
            'another resource and a task',
            $this->labels('en')->describe(['task', 'gigposter', 'poster']),
        );
    }

    private function labels(string $locale): BandSpaceFileAttachmentLabels
    {
        return new BandSpaceFileAttachmentLabels(CatalogueTranslator::in($locale));
    }
}
