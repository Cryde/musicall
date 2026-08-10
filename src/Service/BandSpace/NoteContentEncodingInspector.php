<?php declare(strict_types=1);

namespace App\Service\BandSpace;

/**
 * Decides whether a note body carries the entity encoded text the read path removed by #808 wrote,
 * and what that body should read instead.
 *
 * Until #808, BandSpaceNoteBuilder ran every text node of a note through Symfony's HtmlSanitizer on
 * the way out. The editor renders a text node as a DOM text node, so the member saw the entity
 * itself on screen, and the two second autosave wrote that back. The damage is exactly one
 * application of StringSanitizer::encodeHtmlEntities() per text node:
 *
 *     strtr(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), REPLACEMENTS)
 *
 * so `C'est l'heure` became `C&#039;est l&#039;heure` and `contact@salle.fr` became
 * `contact&#64;salle.fr`. It did not compound: the sanitizer is a fixed point on its own output, so
 * every later read and autosave rewrote the same bytes. The database therefore holds one encoding
 * deep, never two, whatever the number of times the note was opened.
 *
 * The encode half is exactly invertible. strtr() scans once and takes the longest key at each
 * position without reprocessing what it replaced, so decoding `&amp;lt;` yields `&lt;` and not `<`.
 * The parse half is not: the sanitizer decoded `&eacute;` to `é` and dropped `<b>bold</b>` whole,
 * and nothing in the row records that it ever existed. This inspector only undoes the encode half.
 */
final readonly class NoteContentEncodingInspector
{
    /**
     * The exact inverse of encodeHtmlEntities(), so decode(encode($text)) === $text for every input.
     * Order is irrelevant: strtr() takes the longest match at each position in a single pass.
     */
    private const array DECODE = [
        '&amp;' => '&',
        '&lt;' => '<',
        '&gt;' => '>',
        '&#34;' => '"',
        '&#039;' => "'",
        '&#43;' => '+',
        '&#61;' => '=',
        '&#64;' => '@',
        '&#96;' => '`',
        '&#xFF1C;' => '＜',
        '&#xFF1E;' => '＞',
        '&#xFF0B;' => '＋',
        '&#xFF1D;' => '＝',
        '&#xFF20;' => '＠',
        '&#xFF40;' => '｀',
    ];

    /**
     * A copy of StringSanitizer::REPLACEMENTS, which is @internal to the component and cannot be
     * called. It is copied rather than reused on purpose: this is a one shot repair of what the code
     * did in August 2026, so it has to keep matching that, not whatever Symfony ships next.
     * NoteContentEncodingInspectorTest pins it against the real sanitizer.
     */
    private const array REPLACEMENTS = [
        '&quot;' => '&#34;',
        '+' => '&#43;',
        '=' => '&#61;',
        '@' => '&#64;',
        '`' => '&#96;',
        '＜' => '&#xFF1C;',
        '＞' => '&#xFF1E;',
        '＋' => '&#xFF0B;',
        '＝' => '&#xFF1D;',
        '＠' => '&#xFF20;',
        '｀' => '&#xFF40;',
    ];

    /**
     * The entities that only a machine writes, and the whole reason the repair can be attributed.
     *
     * `+`, `=`, `@` and a backtick mean nothing in HTML, so no member and no pasted code sample has a
     * reason to spell them out; Symfony encodes them anyway, to protect unquoted attribute values.
     * `&#039;` and `&#34;` are signatures too: a person writing about entities types `&apos;` or
     * `&quot;`, while htmlspecialchars() pads the apostrophe to three digits and Symfony shortens the
     * quote to `&#34;` because it is one byte less. Every one of them is also normalised on the way
     * in, so `&apos;`, `&#39;` and `&#x27;` all came out as `&#039;`.
     *
     * `&amp;`, `&lt;` and `&gt;` are deliberately absent. They are the three a member writing about
     * markup types by hand, so on their own they prove nothing.
     */
    private const array FINGERPRINTS = [
        '&#34;',
        '&#039;',
        '&#43;',
        '&#61;',
        '&#64;',
        '&#96;',
        '&#xFF1C;',
        '&#xFF1E;',
        '&#xFF0B;',
        '&#xFF1D;',
        '&#xFF20;',
        '&#xFF40;',
    ];

    public const string REVIEW_MIXED = 'carries encoded text next to text the old read path could not have written, so it was edited after the fix';
    public const string REVIEW_STILL_ENCODED = 'still reads as encoded after one decode, so it is either encoded twice or written that way on purpose';
    public const string REVIEW_AMBIGUOUS_ONLY = 'carries only &amp;, &lt; or &gt;, which a member can type by hand, so nothing attributes it to the old read path';

    /**
     * @param array<string, mixed>|null $content
     */
    public function inspect(?array $content): NoteContentEncodingReport
    {
        if ($content === null) {
            return NoteContentEncodingReport::clean();
        }

        /** @var list<string> $texts */
        $texts = [];
        $this->collectTexts($content, $texts);

        $encoded = array_values(array_filter($texts, fn(string $text): bool => $this->decode($text) !== $text));
        if ($encoded === []) {
            return NoteContentEncodingReport::clean();
        }

        // The fingerprint is read over the whole document, not node by node. sanitizeNode() called
        // the sanitizer once per text node, not on the body as a whole, but it walked the tree
        // unconditionally, so every node that existed when the note was read came back encoded. One
        // machine only entity therefore attributes every node already present at that read, and that
        // is what licenses decoding the `&amp;` in a sibling, which on its own would prove nothing.
        //
        // What it does not license: a node added or edited after #808 shipped was never walked by
        // that code, so a pasted `&amp;amp;` sitting beside a leftover corrupted paragraph would be
        // decoded on the word of a sibling that says nothing about it. Those nodes are marked
        // inferred below and reported apart, because a human is the only thing that can tell them
        // from the real ones.
        if (!$this->carriesAnyFingerprint($encoded)) {
            // Every remaining case is a coin flip: `Rock &amp; Roll` reads the same whether the member
            // typed an ampersand and the old read path encoded it, or the member typed the entity.
            return $this->isEncoderOutput($encoded)
                ? NoteContentEncodingReport::forReview(self::REVIEW_AMBIGUOUS_ONLY)
                : NoteContentEncodingReport::clean();
        }

        // A body the sanitizer wrote holds no raw apostrophe, ampersand or `@` anywhere, so one that
        // does was written to after the fix shipped. Its encoded nodes are still repairable, but the
        // note is no longer a single event and a human should look before anything rewrites it.
        if (!$this->isEncoderOutput($encoded)) {
            return NoteContentEncodingReport::forReview(self::REVIEW_MIXED);
        }

        $changes = [];
        foreach ($encoded as $text) {
            $decoded = $this->decode($text);
            // Decoding `&amp;#039;` gives `&#039;`, which would be detected again on the next run.
            // Refusing it keeps the command idempotent by construction, and it is also the right
            // answer: only a member typing `&amp;#039;` produces that, so there is nothing to repair.
            if ($this->carriesFingerprint($decoded)) {
                return NoteContentEncodingReport::forReview(self::REVIEW_STILL_ENCODED);
            }

            $changes[] = [
                'before' => $text,
                'after' => $decoded,
                // A node carrying no fingerprint of its own rests entirely on the sibling argument
                // above, which does not reach a node written after the fix. It is still decoded,
                // because leaving it behind would half repair the body, but it is flagged so the
                // command can put it in front of the operator instead of burying it.
                'inferred' => !$this->carriesFingerprint($text),
            ];
        }

        /** @var array<string, mixed> $repaired */
        $repaired = $this->decodeNode($content);

        return NoteContentEncodingReport::repairable($repaired, $changes);
    }

    /**
     * Walks exactly the nodes the old builder walked, so the repair reaches everything it damaged and
     * nothing else. An image `src` and a link `href` are never touched: only `text` was sanitized.
     *
     * @param array<array-key, mixed> $node
     * @param list<string> $texts
     */
    private function collectTexts(array $node, array &$texts): void
    {
        if (isset($node['text']) && is_string($node['text'])) {
            $texts[] = $node['text'];
        }

        if (!isset($node['content']) || !is_array($node['content'])) {
            return;
        }

        foreach ($node['content'] as $child) {
            if (is_array($child)) {
                $this->collectTexts($child, $texts);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $node
     * @return array<array-key, mixed>
     */
    private function decodeNode(array $node): array
    {
        if (isset($node['text']) && is_string($node['text'])) {
            $node['text'] = $this->decode($node['text']);
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn(mixed $child): mixed => is_array($child) ? $this->decodeNode($child) : $child,
                $node['content'],
            );
        }

        return $node;
    }

    /**
     * Whether every one of these texts is byte for byte something encodeHtmlEntities() can emit. It
     * is checked by re-encoding what was decoded rather than by a pattern, so the answer is the
     * definition itself and cannot drift from it.
     *
     * @param list<string> $texts
     */
    private function isEncoderOutput(array $texts): bool
    {
        foreach ($texts as $text) {
            if ($this->encode($this->decode($text)) !== $text) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $texts
     */
    private function carriesAnyFingerprint(array $texts): bool
    {
        foreach ($texts as $text) {
            if ($this->carriesFingerprint($text)) {
                return true;
            }
        }

        return false;
    }

    private function carriesFingerprint(string $text): bool
    {
        foreach (self::FINGERPRINTS as $fingerprint) {
            if (str_contains($text, $fingerprint)) {
                return true;
            }
        }

        return false;
    }

    private function decode(string $text): string
    {
        return strtr($text, self::DECODE);
    }

    private function encode(string $text): string
    {
        return strtr(htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'), self::REPLACEMENTS);
    }
}
