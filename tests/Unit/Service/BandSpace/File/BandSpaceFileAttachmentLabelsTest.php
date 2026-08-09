<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\File;

use App\Service\BandSpace\File\BandSpaceFileAttachmentLabels;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use PHPUnit\Framework\TestCase;

/**
 * The sentence both delete paths refuse with. The two of them naming the same sources in the same
 * words is the whole point of the class, so the wording is pinned here rather than only through the
 * handful of source types the API tests happen to attach.
 */
class BandSpaceFileAttachmentLabelsTest extends TestCase
{
    public function test_an_empty_list_still_names_something(): void
    {
        $this->assertSame('une autre ressource', BandSpaceFileAttachmentLabels::describe([]));
    }

    /**
     * The five types of BandSpaceFileSourceTypes::ALL, one by one. song and setlist used to fall
     * through to the unknown fallback, which is what this class was extracted to fix.
     */
    public function test_every_source_type_has_its_own_french_noun(): void
    {
        $this->assertSame('une tâche', BandSpaceFileAttachmentLabels::describe(['task']));
        $this->assertSame('une entrée financière', BandSpaceFileAttachmentLabels::describe(['finance']));
        $this->assertSame('une note', BandSpaceFileAttachmentLabels::describe(['note']));
        $this->assertSame('une chanson', BandSpaceFileAttachmentLabels::describe(['song']));
        $this->assertSame('une setlist', BandSpaceFileAttachmentLabels::describe(['setlist']));
    }

    /**
     * Guards the pair against drift: a sixth source type added to the allowlist without a noun here
     * would silently be described as "une autre ressource" in the refusal.
     */
    public function test_no_allowed_source_type_falls_back_to_the_unknown_label(): void
    {
        foreach (BandSpaceFileSourceTypes::ALL as $sourceType) {
            $this->assertNotSame(
                'une autre ressource',
                BandSpaceFileAttachmentLabels::describe([$sourceType]),
                sprintf('Source type "%s" has no French noun', $sourceType),
            );
        }
    }

    public function test_an_unknown_source_type_falls_back(): void
    {
        $this->assertSame('une autre ressource', BandSpaceFileAttachmentLabels::describe(['gigposter']));
    }

    public function test_two_types_are_joined_with_et(): void
    {
        $this->assertSame('une tâche et une note', BandSpaceFileAttachmentLabels::describe(['task', 'note']));
    }

    public function test_three_or_more_types_are_a_comma_list_ending_in_et(): void
    {
        $this->assertSame(
            'une tâche, une note et une chanson',
            BandSpaceFileAttachmentLabels::describe(['task', 'note', 'song']),
        );
        $this->assertSame(
            'une tâche, une note, une chanson et une setlist',
            BandSpaceFileAttachmentLabels::describe(['task', 'note', 'song', 'setlist']),
        );
    }

    /**
     * The caller passes one row per attachment, so the same type arrives many times over: three files
     * attached to three tasks must read "une tâche", not "une tâche, une tâche et une tâche".
     */
    public function test_repeated_types_are_named_once(): void
    {
        $this->assertSame('une tâche', BandSpaceFileAttachmentLabels::describe(['task', 'task', 'task']));
        $this->assertSame(
            'une tâche et une note',
            BandSpaceFileAttachmentLabels::describe(['task', 'note', 'task', 'note']),
        );
    }

    /**
     * Two unknown types are one "une autre ressource", not two, so the list never repeats itself.
     */
    public function test_several_unknown_types_collapse_into_one_fallback(): void
    {
        $this->assertSame(
            'une tâche et une autre ressource',
            BandSpaceFileAttachmentLabels::describe(['task', 'gigposter', 'poster']),
        );
    }
}
