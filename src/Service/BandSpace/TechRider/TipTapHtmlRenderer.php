<?php declare(strict_types=1);

namespace App\Service\BandSpace\TechRider;

use App\Enum\BandSpace\TechRiderColour;

/**
 * Turns a stored TipTap document into HTML for the PDF export.
 *
 * This is the security boundary as much as the renderer, because nothing else is one. A rider's text
 * is stored exactly as the browser sent it: TechRiderItemContentValidator checks only that the value
 * is an array within a depth and a byte budget, and no HtmlSanitizer is wired anywhere in the rider
 * module. The colour allowlist that keeps pasted CSS out of a document lives in the editor, in
 * JavaScript, which a crafted PATCH simply skips.
 *
 * So this works by allowlist rather than by sanitising: it walks the tree and emits only the node
 * types, marks and attribute values it recognises, escaping every piece of text. Anything unknown is
 * dropped rather than cleaned, which means an attribute nobody thought about cannot reach the output
 * by default. That is the opposite bias to a denylist and the reason no sanitiser is needed.
 *
 * The vocabulary is whatever the editor can actually produce, read off the installed packages rather
 * than guessed: StarterKit 3.29 with headings limited to h2 and h3, and note that it bundles link and
 * underline, which the editor's own extension list does not mention. Plus TextAlign on headings and
 * paragraphs, tables whose rows hold only cells, and the TextStyle colour mark the rider adds.
 */
readonly class TipTapHtmlRenderer
{
    /** Everything else is dropped, including images: a rider's text has never been able to hold one. */
    private const array BLOCK_NODES = [
        'paragraph',
        'heading',
        'bulletList',
        'orderedList',
        'listItem',
        'blockquote',
        'codeBlock',
        'horizontalRule',
        'hardBreak',
        'table',
        'tableRow',
        'tableCell',
    ];

    /** The editor is configured for h2 and h3 only, so anything else falls back to the smaller one. */
    private const array HEADING_LEVELS = [2, 3];

    private const array TEXT_ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /** More columns than this in a rider table is a broken document, not a wide one. */
    private const int MAX_CELL_SPAN = 64;

    /**
     * A link in a document sent to a venue becomes a clickable annotation in the PDF, so the scheme
     * is allowlisted. Without this a stored `javascript:` or `data:` href would ship to a stranger.
     */
    private const string HREF_PATTERN = '#^(?:https?://|mailto:)[^\s<>"\']+$#i';

    /** @param array<array-key, mixed>|null $document */
    public function render(?array $document): string
    {
        if ($document === null) {
            return '';
        }

        // A TipTap document is always a `doc` with a content list; anything else is not one.
        if (($document['type'] ?? null) !== 'doc') {
            return '';
        }

        return $this->renderContent($document['content'] ?? null);
    }

    private function renderContent(mixed $content): string
    {
        if (!is_array($content)) {
            return '';
        }

        $html = '';
        foreach ($content as $node) {
            if (is_array($node)) {
                $html .= $this->renderNode($node);
            }
        }

        return $html;
    }

    /** @param array<array-key, mixed> $node */
    private function renderNode(array $node): string
    {
        $type = $node['type'] ?? null;
        if (!is_string($type)) {
            return '';
        }

        if ($type === 'text') {
            return $this->renderText($node);
        }

        if (!in_array($type, self::BLOCK_NODES, true)) {
            return '';
        }

        return match ($type) {
            'horizontalRule' => '<hr>',
            'hardBreak' => '<br>',
            'heading' => $this->wrap($this->headingTag($node), $node, $this->alignmentStyle($node)),
            'paragraph' => $this->wrap('p', $node, $this->alignmentStyle($node)),
            'bulletList' => $this->wrap('ul', $node),
            'orderedList' => $this->wrap('ol', $node),
            'listItem' => $this->wrap('li', $node),
            'blockquote' => $this->wrap('blockquote', $node),
            'codeBlock' => '<pre><code>' . $this->renderTextOnly($node['content'] ?? null) . '</code></pre>',
            'table' => $this->wrap('table', $node),
            'tableRow' => $this->wrap('tr', $node),
            'tableCell' => $this->wrap('td', $node, '', $this->spanAttributes($node)),
        };
    }

    /** @param array<array-key, mixed> $node */
    /**
     * A code block holds text and nothing else, which is what the editor's own schema says. Recursing
     * through the generic dispatch would let a heading or a link nest inside <pre><code>, which the
     * editor could never produce, so the vocabulary this class claims to accept would not be the one
     * it actually accepts. Marks are dropped too: formatting inside a code block is not a thing.
     *
     * @param array<array-key, mixed>|mixed $content
     */
    private function renderTextOnly(mixed $content): string
    {
        if (!is_array($content)) {
            return '';
        }

        $text = '';
        foreach ($content as $node) {
            if (is_array($node) && ($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null)) {
                $text .= htmlspecialchars($node['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        return $text;
    }

    /** @param array<array-key, mixed> $node */
    private function wrap(string $tag, array $node, string $style = '', string $attributes = ''): string
    {
        $styleAttribute = $style === '' ? '' : sprintf(' style="%s"', $style);

        return sprintf(
            '<%1$s%2$s%3$s>%4$s</%1$s>',
            $tag,
            $styleAttribute,
            $attributes,
            $this->renderContent($node['content'] ?? null),
        );
    }

    /**
     * Marks wrap the escaped text from the inside out, in the order the document lists them, so the
     * result is stable for the same input rather than depending on iteration order.
     *
     * @param array<array-key, mixed> $node
     */
    private function renderText(array $node): string
    {
        $text = $node['text'] ?? null;
        if (!is_string($text) || $text === '') {
            return '';
        }

        $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $marks = $node['marks'] ?? null;
        if (!is_array($marks)) {
            return $html;
        }

        foreach ($marks as $mark) {
            if (!is_array($mark)) {
                continue;
            }

            $tags = $this->markTags($mark);
            if ($tags !== null) {
                $html = $tags[0] . $html . $tags[1];
            }
        }

        return $html;
    }

    /**
     * @return array{0: string, 1: string}|null the opening and closing tags, or null to drop the mark
     *
     * @param array<array-key, mixed> $mark
     */
    private function markTags(array $mark): ?array
    {
        return match ($mark['type'] ?? null) {
            'bold' => ['<strong>', '</strong>'],
            'italic' => ['<em>', '</em>'],
            'strike' => ['<s>', '</s>'],
            'underline' => ['<u>', '</u>'],
            'code' => ['<code>', '</code>'],
            'link' => $this->linkTags($mark),
            'textStyle' => $this->colourTags($mark),
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: string}|null
     *
     * @param array<array-key, mixed> $mark
     */
    private function linkTags(array $mark): ?array
    {
        $href = $mark['attrs']['href'] ?? null;
        if (!is_string($href) || preg_match(self::HREF_PATTERN, trim($href)) !== 1) {
            // The text survives, only the link is dropped: a venue reading the printed rider still
            // gets the words, and an unusable scheme never becomes an annotation.
            return null;
        }

        return [
            sprintf('<a href="%s">', htmlspecialchars(trim($href), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            '</a>',
        ];
    }

    /**
     * The palette and nothing else. This is the first place the allowlist is enforced anywhere but the
     * browser, so a colour smuggled in by a crafted request stops here.
     *
     * @return array{0: string, 1: string}|null
     *
     * @param array<array-key, mixed> $mark
     */
    private function colourTags(array $mark): ?array
    {
        $colour = $mark['attrs']['color'] ?? null;
        if (!is_string($colour)) {
            return null;
        }

        foreach (TechRiderColour::cases() as $case) {
            if (strcasecmp($case->hex(), trim($colour)) === 0) {
                return [sprintf('<span style="color:%s">', $case->hex()), '</span>'];
            }
        }

        return null;
    }

    /** @param array<array-key, mixed> $node */
    private function headingTag(array $node): string
    {
        $level = $node['attrs']['level'] ?? null;

        return in_array($level, self::HEADING_LEVELS, true) ? 'h' . $level : 'h3';
    }

    /** @param array<array-key, mixed> $node */
    private function alignmentStyle(array $node): string
    {
        $alignment = $node['attrs']['textAlign'] ?? null;

        return is_string($alignment) && in_array($alignment, self::TEXT_ALIGNMENTS, true)
            ? 'text-align:' . $alignment
            : '';
    }

    /** Only positive integers, and only when they actually span, so the markup stays minimal. *
     * @param array<array-key, mixed> $node
     */
    private function spanAttributes(array $node): string
    {
        $attributes = '';
        foreach (['colspan', 'rowspan'] as $name) {
            $span = $node['attrs'][$name] ?? null;
            // Upper bound as well as lower, so the one numeric attribute this class emits is as
            // bounded as every other value it lets through.
            if (is_int($span) && $span > 1 && $span <= self::MAX_CELL_SPAN) {
                $attributes .= sprintf(' %s="%d"', $name, $span);
            }
        }

        return $attributes;
    }
}
