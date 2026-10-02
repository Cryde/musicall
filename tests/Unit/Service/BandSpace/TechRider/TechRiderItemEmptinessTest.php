<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\BandSpace\TechRiderPatchRow;
use App\Enum\BandSpace\TechRiderItemType;
use App\Service\BandSpace\TechRider\TechRiderContactsRenderer;
use App\Service\BandSpace\TechRider\TechRiderItemEmptiness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What counts as "nothing to print" (#1090). The PDF leaves such an item out and the editor marks it,
 * so a wrong answer either drops a section the band wrote or prints a heading over nothing.
 */
class TechRiderItemEmptinessTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $content
     */
    #[DataProvider('textProvider')]
    public function test_a_text_item_is_empty_until_it_holds_visible_text(?array $content, bool $isEmpty): void
    {
        $item = $this->item(TechRiderItemType::Text, $content);

        $this->assertSame($isEmpty, $this->emptiness()->isEmpty($item));
    }

    /** @return iterable<string, array{0: array<string, mixed>|null, 1: bool}> */
    public static function textProvider(): iterable
    {
        yield 'never written' => [null, true];
        yield 'an empty document' => [['type' => 'doc', 'content' => []], true];
        yield 'empty paragraphs' => [['type' => 'doc', 'content' => [['type' => 'paragraph'], ['type' => 'paragraph']]], true];
        yield 'whitespace only' => [self::doc(self::paragraph('   ')), true];
        yield 'a table of blank cells' => [self::doc(['type' => 'table', 'content' => [
            ['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'content' => [['type' => 'paragraph']]]]],
        ]]), true];
        yield 'a word' => [self::doc(self::paragraph('Catering')), false];
        yield 'a word deep in a table' => [self::doc(['type' => 'table', 'content' => [
            ['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'content' => [self::paragraph('Wedge')]]]],
        ]]), false];
    }

    public function test_a_stage_plot_is_empty_without_an_element(): void
    {
        $this->assertTrue($this->emptiness()->isEmpty($this->item(TechRiderItemType::StagePlot, null)));
        $this->assertTrue($this->emptiness()->isEmpty($this->item(TechRiderItemType::StagePlot, ['version' => 1, 'elements' => []])));
        $this->assertFalse($this->emptiness()->isEmpty($this->item(TechRiderItemType::StagePlot, [
            'version' => 1,
            'elements' => [['id' => 'a', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.5]],
        ])));
    }

    /** The PDF skips an element whose icon it does not know, so a plot of only those prints nothing. */
    public function test_a_stage_plot_of_unknown_icons_is_empty(): void
    {
        $this->assertTrue($this->emptiness()->isEmpty($this->item(TechRiderItemType::StagePlot, [
            'version' => 1,
            'elements' => [['id' => 'a', 'icon' => 'retired_icon', 'x' => 0.5, 'y' => 0.5], 'not an element'],
        ])));
    }

    public function test_contact_lines_already_rendered_are_used_rather_than_queried_again(): void
    {
        $contacts = $this->createMock(TechRiderContactsRenderer::class);
        $contacts->expects($this->never())->method('render');

        $this->assertFalse((new TechRiderItemEmptiness($contacts))->isEmpty($this->item(TechRiderItemType::Contacts, null), ['Léa']));
    }

    public function test_a_patch_list_is_empty_without_a_row(): void
    {
        $item = $this->item(TechRiderItemType::PatchList, null);
        $this->assertTrue($this->emptiness()->isEmpty($item));

        $item->patchRows->add(new TechRiderPatchRow());
        $this->assertFalse($this->emptiness()->isEmpty($item));
    }

    public function test_a_document_is_empty_without_a_file(): void
    {
        $item = $this->item(TechRiderItemType::Document, null);
        $this->assertTrue($this->emptiness()->isEmpty($item));

        $item->file = $this->createStub(BandSpaceFile::class);
        $this->assertFalse($this->emptiness()->isEmpty($item));
    }

    public function test_contacts_are_empty_only_with_no_member_and_no_note(): void
    {
        $nobody = $this->emptiness(lines: []);

        $this->assertTrue($nobody->isEmpty($this->item(TechRiderItemType::Contacts, null)));
        $this->assertFalse($nobody->isEmpty($this->item(TechRiderItemType::Contacts, ['note' => self::doc(self::paragraph('Régie : Sam'))])));
        $this->assertFalse($this->emptiness(lines: ['Léa / Guitare'])->isEmpty($this->item(TechRiderItemType::Contacts, null)));
    }

    /** @param list<string> $lines */
    private function emptiness(array $lines = ['Léa / Guitare']): TechRiderItemEmptiness
    {
        $contacts = $this->createStub(TechRiderContactsRenderer::class);
        $contacts->method('render')->willReturn(['lines' => $lines, 'emails' => []]);

        return new TechRiderItemEmptiness($contacts);
    }

    /** @param array<string, mixed>|null $content */
    private function item(TechRiderItemType $type, ?array $content): TechRiderItem
    {
        $rider = new TechRider();
        $rider->bandSpace = new BandSpace();
        $item = new TechRiderItem();
        $item->techRider = $rider;
        $item->type = $type;
        $item->content = $content;

        return $item;
    }

    /** @return array<string, mixed> */
    private static function doc(array ...$nodes): array
    {
        return ['type' => 'doc', 'content' => $nodes];
    }

    /** @return array<string, mixed> */
    private static function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
    }
}
