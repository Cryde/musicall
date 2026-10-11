<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\RichText;

use App\Service\BandSpace\RichText\TipTapDocumentChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TipTapDocumentCheckerTest extends TestCase
{
    private TipTapDocumentChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new TipTapDocumentChecker();
    }

    private static function doc(array ...$content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    private static function paragraph(array ...$content): array
    {
        return ['type' => 'paragraph', 'attrs' => ['textAlign' => null], 'content' => $content];
    }

    private static function text(string $text, array ...$marks): array
    {
        return $marks === []
            ? ['type' => 'text', 'text' => $text]
            : ['type' => 'text', 'text' => $text, 'marks' => $marks];
    }

    /** Every node and mark the shared editor produces, with the attributes TipTap writes on them. */
    private static function everythingTheEditorWrites(): array
    {
        return self::doc(
            ['type' => 'heading', 'attrs' => ['textAlign' => 'center', 'level' => 2], 'content' => [self::text('Son')]],
            ['type' => 'heading', 'attrs' => ['textAlign' => null, 'level' => 3], 'content' => [self::text('Retours')]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'justify'], 'content' => [
                self::text('gras', ['type' => 'bold']),
                self::text('italique', ['type' => 'italic']),
                self::text('barré', ['type' => 'strike']),
                self::text('souligné', ['type' => 'underline']),
                self::text('code', ['type' => 'code']),
                self::text('lien', ['type' => 'link', 'attrs' => ['href' => 'https://musicall.com', 'target' => '_blank', 'rel' => 'noopener noreferrer nofollow', 'class' => null]]),
                self::text('mail', ['type' => 'link', 'attrs' => ['href' => 'mailto:son@example.com']]),
                ['type' => 'hardBreak'],
            ]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [self::paragraph(self::text('Un'))]]]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1, 'type' => null], 'content' => [['type' => 'listItem', 'content' => [self::paragraph(self::text('Deux'))]]]],
            ['type' => 'blockquote', 'content' => [self::paragraph(self::text('Cité'))]],
            ['type' => 'codeBlock', 'attrs' => ['language' => null], 'content' => [self::text('ligne')]],
            ['type' => 'horizontalRule'],
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [
                ['type' => 'tableCell', 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null], 'content' => [self::paragraph(self::text('Voie'))]],
            ]]]],
            // An empty paragraph carries no content key at all.
            ['type' => 'paragraph', 'attrs' => ['textAlign' => null]],
        );
    }

    public function test_everything_the_editor_writes_is_in_the_format(): void
    {
        $this->assertNull($this->checker->findProblem(self::everythingTheEditorWrites(), allowsImages: false, allowsColour: false));
        $this->assertNull($this->checker->findProblem(self::everythingTheEditorWrites(), allowsImages: true, allowsColour: true));
    }

    public function test_an_empty_document_is_in_the_format(): void
    {
        $this->assertNull($this->checker->findProblem(['type' => 'doc'], false, false));
        $this->assertNull($this->checker->findProblem(self::doc(), false, false));
    }

    public function test_a_deeply_nested_document_is_walked_without_recursing(): void
    {
        $node = self::paragraph(self::text('fond'));
        for ($level = 0; $level < 2000; ++$level) {
            $node = ['type' => 'blockquote', 'content' => [$node]];
        }

        $this->assertNull($this->checker->findProblem(self::doc($node), false, false));
    }

    #[DataProvider('outOfFormatProvider')]
    public function test_a_document_out_of_the_format_is_refused(array $document, string $expected): void
    {
        $this->assertSame($expected, $this->checker->findProblem($document, allowsImages: true, allowsColour: false));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function outOfFormatProvider(): iterable
    {
        // The four shapes the API stored verbatim before #1012.
        yield 'another root type' => [['type' => 'shopping-list', 'content' => 'whatever'], 'Le contenu n\'est pas un document valide'];
        yield 'an unknown node' => [self::doc(['type' => 'blink', 'attrs' => ['evil' => true]]), 'Le contenu contient un élément non pris en charge (« blink »)'];
        yield 'no root type' => [['hello' => 'world', 'nested' => ['deep' => [1, 2, 3]]], 'Le contenu n\'est pas un document valide'];
        yield 'a node with no type' => [self::doc(['content' => [['text' => 'orphan']]]), 'Le contenu contient un élément sans type'];

        yield 'a root content that is not a list' => [['type' => 'doc', 'content' => ['a' => self::paragraph()]], 'Le contenu n\'est pas un document valide'];
        yield 'a node that is not an object' => [['type' => 'doc', 'content' => [self::paragraph(), 'texte']], 'Le contenu n\'est pas un document valide'];
        yield 'a nested doc' => [self::doc(['type' => 'doc', 'content' => []]), 'Le contenu contient un élément non pris en charge (« doc »)'];
        yield 'a text node with no text' => [self::doc(self::paragraph(['type' => 'text'])), 'Le contenu n\'est pas un document valide'];
        yield 'an empty text node' => [self::doc(self::paragraph(['type' => 'text', 'text' => ''])), 'Le contenu n\'est pas un document valide'];
        yield 'a long node type is quoted short' => [self::doc(['type' => str_repeat('x', 500)]), 'Le contenu contient un élément non pris en charge (« ' . str_repeat('x', 40) . ' »)'];

        // Standard TipTap that this editor is configured not to produce.
        yield 'a table header' => [self::doc(['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableHeader']]]]]), 'Le contenu contient un élément non pris en charge (« tableHeader »)'];
        yield 'a level 1 heading' => [self::doc(['type' => 'heading', 'attrs' => ['level' => 1]]), 'Les titres doivent être de niveau 2 ou 3'];
        yield 'a heading level as a string' => [self::doc(['type' => 'heading', 'attrs' => ['level' => '2']]), 'Les titres doivent être de niveau 2 ou 3'];
        yield 'a heading with no level' => [self::doc(['type' => 'heading']), 'Les titres doivent être de niveau 2 ou 3'];
        yield 'an unknown alignment' => [self::doc(['type' => 'paragraph', 'attrs' => ['textAlign' => 'start']]), 'Le contenu contient un alignement non pris en charge'];
        yield 'an alignment on a list item' => [self::doc(['type' => 'bulletList', 'content' => [['type' => 'listItem', 'attrs' => ['textAlign' => 'center']]]]), 'Le contenu contient un alignement non pris en charge'];
        yield 'attributes that are not an object' => [self::doc(['type' => 'paragraph', 'attrs' => 'x']), 'Le contenu n\'est pas un document valide'];

        yield 'an unknown mark' => [self::doc(self::paragraph(self::text('x', ['type' => 'highlight']))), 'Le contenu contient une mise en forme non prise en charge (« highlight »)'];
        yield 'a mark with no type' => [self::doc(self::paragraph(self::text('x', ['attrs' => []]))), 'Le contenu n\'est pas un document valide'];
        yield 'marks that are not a list' => [self::doc(self::paragraph(['type' => 'text', 'text' => 'x', 'marks' => ['type' => 'bold']])), 'Le contenu n\'est pas un document valide'];
        yield 'a colour where colours are refused' => [self::doc(self::paragraph(self::text('x', ['type' => 'textStyle', 'attrs' => ['color' => '#dc2626']]))), 'Le contenu contient une mise en forme non prise en charge (« textStyle »)'];
    }

    /**
     * What the editor saves from a paste: TipTap's image extension keeps any `src` but `data:`, and
     * its link extension allows `tel:` and `ftp:`. Refusing them would stop the member's autosave,
     * so the URIs are left to the read side filters.
     */
    public function test_link_and_image_targets_the_editor_saves_are_in_the_format(): void
    {
        $document = self::doc(
            ['type' => 'image', 'attrs' => ['src' => 'https://images.example/affiche.jpg', 'alt' => null, 'title' => null]],
            self::paragraph(self::text('appeler', ['type' => 'link', 'attrs' => ['href' => 'tel:+33612345678']])),
        );

        $this->assertNull($this->checker->findProblem($document, allowsImages: true, allowsColour: false));
    }

    public function test_an_image_is_refused_where_images_are(): void
    {
        $image = ['type' => 'image', 'attrs' => ['src' => 'https://images.example/affiche.jpg']];

        $this->assertSame('Le contenu contient un élément non pris en charge (« image »)', $this->checker->findProblem(self::doc($image), allowsImages: false, allowsColour: false));
    }

    /** Empty is what a pasted `<span style="font-weight:400">` becomes: a span styled without a colour. */
    public function test_a_palette_colour_is_in_a_rider_text_whatever_its_case(): void
    {
        foreach (['#dc2626', '#DC2626', null, ''] as $colour) {
            $document = self::doc(self::paragraph(self::text('x', ['type' => 'textStyle', 'attrs' => ['color' => $colour]])));

            $this->assertNull($this->checker->findProblem($document, allowsImages: false, allowsColour: true), (string) $colour);
        }
    }

    public function test_a_colour_off_the_palette_is_refused(): void
    {
        $document = self::doc(self::paragraph(self::text('x', ['type' => 'textStyle', 'attrs' => ['color' => '#123456']])));

        $this->assertSame('Le contenu contient une couleur hors de la palette', $this->checker->findProblem($document, allowsImages: false, allowsColour: true));
    }
}
