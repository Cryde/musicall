<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\TechRider;

use App\Enum\BandSpace\TechRiderColour;
use App\Service\BandSpace\TechRider\TipTapHtmlRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rider's text is stored exactly as the browser sent it, so this renderer is the only thing
 * standing between that JSON and a document sent to a venue. Most of what follows is therefore about
 * what does *not* come out.
 */
class TipTapHtmlRendererTest extends TestCase
{
    private TipTapHtmlRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TipTapHtmlRenderer();
    }

    private static function doc(array ...$content): array
    {
        return ['type' => 'doc', 'content' => $content];
    }

    private static function text(string $text, array ...$marks): array
    {
        return $marks === []
            ? ['type' => 'text', 'text' => $text]
            : ['type' => 'text', 'text' => $text, 'marks' => $marks];
    }

    // ---------------------------------------------------------------- the vocabulary

    public function test_a_paragraph_of_plain_text(): void
    {
        $html = $this->renderer->render(self::doc(['type' => 'paragraph', 'content' => [self::text('Deux wedges')]]));

        $this->assertSame('<p>Deux wedges</p>', $html);
    }

    #[DataProvider('supportedMarkProvider')]
    public function test_each_supported_mark_wraps_the_text(array $mark, string $expected): void
    {
        $html = $this->renderer->render(self::doc(['type' => 'paragraph', 'content' => [self::text('x', $mark)]]));

        $this->assertSame('<p>' . $expected . '</p>', $html);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function supportedMarkProvider(): iterable
    {
        yield 'bold' => [['type' => 'bold'], '<strong>x</strong>'];
        yield 'italic' => [['type' => 'italic'], '<em>x</em>'];
        yield 'strike' => [['type' => 'strike'], '<s>x</s>'];
        // Underline ships inside StarterKit even though the editor's extension list never names it.
        yield 'underline' => [['type' => 'underline'], '<u>x</u>'];
        yield 'code' => [['type' => 'code'], '<code>x</code>'];
    }

    #[DataProvider('supportedNodeProvider')]
    public function test_each_supported_node_renders(array $node, string $expected): void
    {
        $this->assertSame($expected, $this->renderer->render(self::doc($node)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function supportedNodeProvider(): iterable
    {
        yield 'h2' => [['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [self::text('Son')]], '<h2>Son</h2>'];
        yield 'h3' => [['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [self::text('Son')]], '<h3>Son</h3>'];
        yield 'horizontal rule' => [['type' => 'horizontalRule'], '<hr>'];
        yield 'hard break' => [['type' => 'hardBreak'], '<br>'];
        yield 'blockquote' => [
            ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [self::text('a')]]]],
            '<blockquote><p>a</p></blockquote>',
        ];
        yield 'code block' => [
            ['type' => 'codeBlock', 'content' => [self::text('48 kHz')]],
            '<pre><code>48 kHz</code></pre>',
        ];
        yield 'bullet list' => [
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [self::text('a')]]]]]],
            '<ul><li><p>a</p></li></ul>',
        ];
        yield 'ordered list' => [
            ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [self::text('a')]]]]]],
            '<ol><li><p>a</p></li></ol>',
        ];
        yield 'table' => [
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'content' => [self::text('a')]]]]]],
            '<table><tr><td>a</td></tr></table>',
        ];
    }

    /** The editor only offers h2 and h3, so anything else becomes the smaller of the two. */
    public function test_an_unexpected_heading_level_falls_back_rather_than_emitting_it(): void
    {
        $html = $this->renderer->render(self::doc(['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [self::text('Titre')]]));

        $this->assertSame('<h3>Titre</h3>', $html);
    }

    public function test_a_cell_span_is_emitted_only_when_it_actually_spans(): void
    {
        $cell = static fn (array $attrs): array => ['type' => 'table', 'content' => [
            ['type' => 'tableRow', 'content' => [['type' => 'tableCell', 'attrs' => $attrs, 'content' => [self::text('a')]]]],
        ]];

        $this->assertStringContainsString('<td colspan="2">', $this->renderer->render(self::doc($cell(['colspan' => 2]))));
        $this->assertStringContainsString('<td>', $this->renderer->render(self::doc($cell(['colspan' => 1]))));
        // A string span is not a span. Emitting it unchecked would put arbitrary text in an attribute.
        $this->assertStringContainsString('<td>', $this->renderer->render(self::doc($cell(['colspan' => '2" onload="x']))));
    }

    #[DataProvider('alignmentProvider')]
    public function test_text_alignment(mixed $alignment, string $expected): void
    {
        $html = $this->renderer->render(self::doc([
            'type' => 'paragraph',
            'attrs' => ['textAlign' => $alignment],
            'content' => [self::text('a')],
        ]));

        $this->assertSame($expected, $html);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function alignmentProvider(): iterable
    {
        yield 'centre' => ['center', '<p style="text-align:center">a</p>'];
        yield 'justify' => ['justify', '<p style="text-align:justify">a</p>'];
        yield 'an unknown keyword is dropped' => ['diagonal', '<p>a</p>'];
        yield 'a smuggled declaration is dropped' => ['left;position:fixed;top:0', '<p>a</p>'];
        yield 'a non string is dropped' => [42, '<p>a</p>'];
    }

    // ---------------------------------------------------------------- what must not come out

    public function test_text_is_escaped(): void
    {
        $html = $this->renderer->render(self::doc([
            'type' => 'paragraph',
            'content' => [self::text('<script>alert(1)</script> & "quoted"')],
        ]));

        $this->assertSame(
            '<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;quoted&quot;</p>',
            $html,
        );
    }

    /**
     * The allowlist bias: an unknown node contributes nothing, rather than being cleaned up and kept.
     * An image is the realistic case, since the package is installed and another editor uses it.
     */
    public function test_an_unknown_node_is_dropped_whole(): void
    {
        $html = $this->renderer->render(self::doc(
            ['type' => 'image', 'attrs' => ['src' => 'https://elsewhere.test/tracker.png']],
            ['type' => 'paragraph', 'content' => [self::text('après')]],
        ));

        $this->assertSame('<p>après</p>', $html);
    }

    public function test_an_unknown_mark_is_dropped_but_its_text_survives(): void
    {
        $html = $this->renderer->render(self::doc([
            'type' => 'paragraph',
            'content' => [self::text('important', ['type' => 'highlight', 'attrs' => ['color' => 'red']])],
        ]));

        $this->assertSame('<p>important</p>', $html);
    }

    #[DataProvider('paletteProvider')]
    public function test_a_palette_colour_is_kept(TechRiderColour $colour): void
    {
        $html = $this->renderer->render(self::doc([
            'type' => 'paragraph',
            'content' => [self::text('a', ['type' => 'textStyle', 'attrs' => ['color' => $colour->hex()]])],
        ]));

        $this->assertSame(sprintf('<p><span style="color:%s">a</span></p>', $colour->hex()), $html);
    }

    /**
     * @return iterable<string, array{TechRiderColour}>
     */
    public static function paletteProvider(): iterable
    {
        foreach (TechRiderColour::cases() as $colour) {
            yield $colour->value => [$colour];
        }
    }

    /**
     * The hole this closes. The palette is enforced in the editor, in JavaScript, so a crafted PATCH
     * can already store any colour string it likes; this is the first place that stops.
     */
    #[DataProvider('rejectedColourProvider')]
    public function test_a_colour_outside_the_palette_is_dropped(mixed $colour): void
    {
        $html = $this->renderer->render(self::doc([
            'type' => 'paragraph',
            'content' => [self::text('a', ['type' => 'textStyle', 'attrs' => ['color' => $colour]])],
        ]));

        $this->assertSame('<p>a</p>', $html);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function rejectedColourProvider(): iterable
    {
        yield 'an arbitrary hex' => ['#123456'];
        yield 'a named colour' => ['rebeccapurple'];
        yield 'a smuggled declaration' => ['#dc2626;position:fixed;left:0'];
        yield 'a url' => ['url(https://elsewhere.test/x)'];
        yield 'a closed quote' => ['#dc2626"><script>alert(1)</script>'];
        yield 'a non string' => [['#dc2626']];
    }

    #[DataProvider('linkProvider')]
    public function test_only_a_usable_scheme_becomes_a_link(string $href, ?string $expectedHref): void
    {
        $html = $this->renderer->render(self::doc([
            'type' => 'paragraph',
            'content' => [self::text('ici', ['type' => 'link', 'attrs' => ['href' => $href]])],
        ]));

        $expected = $expectedHref === null
            ? '<p>ici</p>'
            : sprintf('<p><a href="%s">ici</a></p>', $expectedHref);

        $this->assertSame($expected, $html);
    }

    /**
     * A link becomes a clickable annotation in the PDF a venue receives, which is why the scheme is
     * allowlisted rather than merely escaped.
     *
     * @return iterable<string, array{string, string|null}>
     */
    public static function linkProvider(): iterable
    {
        yield 'https' => ['https://musicall.test/rider', 'https://musicall.test/rider'];
        yield 'http' => ['http://musicall.test', 'http://musicall.test'];
        yield 'mailto' => ['mailto:regie@musicall.test', 'mailto:regie@musicall.test'];
        yield 'surrounding whitespace is trimmed' => ['  https://musicall.test  ', 'https://musicall.test'];
        yield 'javascript is refused' => ['javascript:alert(1)', null];
        yield 'javascript behind whitespace is refused' => ['   javascript:alert(1)', null];
        yield 'a data uri is refused' => ['data:text/html;base64,PHNjcmlwdD4=', null];
        yield 'a file uri is refused' => ['file:///etc/passwd', null];
        yield 'a relative path is refused' => ['/band/secrets', null];
        yield 'an attribute break is refused' => ['https://musicall.test" onmouseover="x', null];
        yield 'a scheme relative url is refused' => ['//elsewhere.test/x', null];
        yield 'an embedded newline is refused' => ["https://musicall.test\njavascript:alert(1)", null];
        yield 'an embedded carriage return is refused' => ["https://musicall.test\r\nx", null];
    }

    // ---------------------------------------------------------------- shapes that are not documents

    #[DataProvider('nonDocumentProvider')]
    public function test_anything_that_is_not_a_document_renders_nothing(?array $document): void
    {
        $this->assertSame('', $this->renderer->render($document));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>|null}>
     */
    public static function nonDocumentProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'the wrong root type' => [['type' => 'paragraph', 'content' => []]];
        yield 'a document with no content' => [['type' => 'doc']];
        yield 'a document whose content is not a list' => [['type' => 'doc', 'content' => 'text']];
        yield 'a node with no type' => [['type' => 'doc', 'content' => [['content' => []]]]];
        yield 'a text node with no text' => [['type' => 'doc', 'content' => [['type' => 'text']]]];
    }

    /**
     * A code block holds text, which is what the editor's schema says. Recursing through the generic
     * dispatch would let a heading or a working link live inside <pre><code>, which the editor could
     * never produce, so the vocabulary claimed would not be the vocabulary accepted.
     */
    public function test_a_code_block_holds_text_and_nothing_else(): void
    {
        $html = $this->renderer->render(self::doc(['type' => 'codeBlock', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [self::text('Titre')]],
            self::text('48 kHz <ok>', ['type' => 'link', 'attrs' => ['href' => 'https://elsewhere.test']]),
        ]]));

        $this->assertSame('<pre><code>48 kHz &lt;ok&gt;</code></pre>', $html);
    }

    /** Bounded above as well as below, like every other value this class lets through. */
    public function test_an_absurd_cell_span_is_dropped(): void
    {
        $html = $this->renderer->render(self::doc(['type' => 'table', 'content' => [
            ['type' => 'tableRow', 'content' => [
                ['type' => 'tableCell', 'attrs' => ['colspan' => 999999], 'content' => [self::text('a')]],
            ]],
        ]]));

        $this->assertSame('<table><tr><td>a</td></tr></table>', $html);
    }

    /** Nesting is bounded by the validator at 40 levels, so the renderer only has to not fall over. */
    public function test_a_deeply_nested_document_renders(): void
    {
        $node = ['type' => 'paragraph', 'content' => [self::text('fond')]];
        for ($depth = 0; $depth < 30; ++$depth) {
            $node = ['type' => 'blockquote', 'content' => [$node]];
        }

        $html = $this->renderer->render(self::doc($node));

        $this->assertSame(30, substr_count($html, '<blockquote>'));
        $this->assertStringContainsString('<p>fond</p>', $html);
    }
}
