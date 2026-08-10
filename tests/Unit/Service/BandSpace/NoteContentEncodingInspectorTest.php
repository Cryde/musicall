<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace;

use App\Service\BandSpace\NoteContentEncodingInspector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The detection rule of #859, which is the whole risk of the repair.
 *
 * Rewriting a note body is easy; deciding that it should be rewritten is not. A member who wrote
 * about markup, or pasted a code sample, typed entities on purpose, and a repair that decodes those
 * corrupts a note that was never broken. These tests pin where the line sits and, just as much,
 * which cases the inspector refuses to decide.
 */
class NoteContentEncodingInspectorTest extends TestCase
{
    private NoteContentEncodingInspector $inspector;

    protected function setUp(): void
    {
        $this->inspector = new NoteContentEncodingInspector();
    }

    public function test_null_body_is_clean(): void
    {
        $report = $this->inspector->inspect(null);

        $this->assertFalse($report->isRepairable());
        $this->assertFalse($report->needsReview());
    }

    public function test_repairs_a_body_the_old_read_path_encoded(): void
    {
        $report = $this->inspector->inspect($this->doc(
            'C&#039;est l&#039;heure de la répét',
            'Écrire à contact&#64;salle.fr avant le 12/03',
        ));

        $this->assertTrue($report->isRepairable());
        $this->assertSame(
            ["C'est l'heure de la répét", 'Écrire à contact@salle.fr avant le 12/03'],
            $this->textsOf($report->repairedContent),
        );
        $this->assertFalse($report->isInferred());
        $this->assertSame([
            ['before' => 'C&#039;est l&#039;heure de la répét', 'after' => "C'est l'heure de la répét", 'inferred' => false],
            ['before' => 'Écrire à contact&#64;salle.fr avant le 12/03', 'after' => 'Écrire à contact@salle.fr avant le 12/03', 'inferred' => false],
        ], $report->changes);
    }

    /**
     * The one thing the sibling argument cannot carry, and the reason a repair resting on it is never
     * reported next to a proven one.
     *
     * The pre #808 walk was unconditional over the nodes present when the note was read, so a machine
     * only entity does attribute its neighbours. It says nothing about a paragraph written after the
     * fix, and `&amp;amp;` pasted from a view source, a chat or an email export is exactly what such a
     * paragraph can hold. Both readings of this text stay live, so the same bytes have to be
     * classified by what sits next to them, and the operator is the one who decides.
     */
    public function test_the_same_ambiguous_text_is_classified_by_what_attributes_it(): void
    {
        $pasted = 'Le concert AC&amp;DC est complet ce soir';

        $alone = $this->inspector->inspect($this->doc($pasted));

        $this->assertFalse($alone->isRepairable());
        $this->assertTrue($alone->needsReview());
        $this->assertSame(NoteContentEncodingInspector::REVIEW_AMBIGUOUS_ONLY, $alone->reviewReason);

        $besideAFingerprint = $this->inspector->inspect($this->doc(
            'C&#039;est l&#039;heure de la balance ce soir.',
            $pasted,
        ));

        $this->assertTrue($besideAFingerprint->isRepairable());
        $this->assertTrue($besideAFingerprint->isInferred());
        $this->assertSame([
            [
                'before' => 'C&#039;est l&#039;heure de la balance ce soir.',
                'after' => "C'est l'heure de la balance ce soir.",
                'inferred' => false,
            ],
            [
                'before' => $pasted,
                'after' => 'Le concert AC&DC est complet ce soir',
                'inferred' => true,
            ],
        ], $besideAFingerprint->changes);
    }

    public function test_leaves_a_clean_french_body_alone(): void
    {
        $report = $this->inspector->inspect($this->doc(
            "C'est l'heure de la répét « Rock & Roll »",
            'Écrire à contact@salle.fr, 2+2=4, et on dit "oui" à 100 %',
        ));

        $this->assertFalse($report->isRepairable());
        $this->assertFalse($report->needsReview());
    }

    /**
     * The case the repair exists not to break. `&amp;` is what a member writing about markup types,
     * and nothing in the row says whether it was typed or encoded, so the note is named and left.
     */
    public function test_leaves_a_body_carrying_only_hand_typable_entities_for_review(): void
    {
        $report = $this->inspector->inspect($this->doc(
            'Pour afficher une esperluette en HTML, tapez &amp;',
            'Une balise ouvre sur &lt; et ferme sur &gt;',
        ));

        $this->assertFalse($report->isRepairable());
        $this->assertTrue($report->needsReview());
        $this->assertSame(NoteContentEncodingInspector::REVIEW_AMBIGUOUS_ONLY, $report->reviewReason);
    }

    /**
     * Same entities, but sitting next to an apostrophe the sanitizer would have encoded. That proves
     * the body never went through it, so the entities are the author's and there is nothing to flag.
     */
    public function test_leaves_hand_typed_entities_next_to_raw_text_alone(): void
    {
        $report = $this->inspector->inspect($this->doc(
            "Pour l'esperluette, tapez &amp; dans l'éditeur",
        ));

        $this->assertFalse($report->isRepairable());
        $this->assertFalse($report->needsReview());
    }

    public function test_leaves_a_pasted_code_sample_alone(): void
    {
        $report = $this->inspector->inspect([
            'type' => 'doc',
            'content' => [[
                'type' => 'codeBlock',
                'attrs' => ['language' => 'php'],
                'content' => [['type' => 'text', 'text' => 'if ($a < $b && $c > $d) { return "ok"; }']],
            ]],
        ]);

        $this->assertFalse($report->isRepairable());
        $this->assertFalse($report->needsReview());
    }

    /**
     * A code sample that went through the old read path is repaired like any other text: the
     * sanitizer ran on every text node, code block or not.
     */
    public function test_repairs_a_code_sample_the_old_read_path_encoded(): void
    {
        $report = $this->inspector->inspect([
            'type' => 'doc',
            'content' => [[
                'type' => 'codeBlock',
                'attrs' => ['language' => 'php'],
                'content' => [['type' => 'text', 'text' => 'if ($a &lt; $b &amp;&amp; $c &gt; $d) { return &#34;ok&#34;; }']],
            ]],
        ]);

        $this->assertTrue($report->isRepairable());
        $this->assertFalse($report->isInferred());
        $this->assertSame(['if ($a < $b && $c > $d) { return "ok"; }'], $this->textsOf($report->repairedContent));
    }

    /**
     * The body is repaired whole rather than paragraph by paragraph, because leaving the two nodes
     * that carry no fingerprint of their own behind would produce a note reading half repaired with
     * nothing on the row to say why. They are still marked inferred, one by one, so the command can
     * put them in front of the operator.
     */
    public function test_repairs_hand_typable_entities_once_a_sibling_attributes_them(): void
    {
        $report = $this->inspector->inspect($this->doc(
            'C&#039;est reparti',
            'Rock &amp; Roll',
            'a &lt; b',
        ));

        $this->assertTrue($report->isRepairable());
        $this->assertTrue($report->isInferred());
        $this->assertSame(
            ["C'est reparti", 'Rock & Roll', 'a < b'],
            $this->textsOf($report->repairedContent),
        );
        $this->assertSame([false, true, true], array_column($report->changes, 'inferred'));
    }

    public function test_a_repaired_body_is_untouched_on_a_second_pass(): void
    {
        $first = $this->inspector->inspect($this->doc(
            'C&#039;est l&#039;heure',
            'Rock &amp; Roll',
            'Écrire à contact&#64;salle.fr',
        ));
        $this->assertTrue($first->isRepairable());

        $second = $this->inspector->inspect($first->repairedContent);

        $this->assertFalse($second->isRepairable());
        $this->assertFalse($second->needsReview());
    }

    /**
     * A body the sanitizer wrote holds no raw apostrophe anywhere, so one that does was written to
     * after #808 shipped. Its encoded nodes are still repairable, but the body is no longer a single
     * event and the command hands it to a human rather than deciding on its own.
     */
    public function test_leaves_a_body_edited_after_the_fix_for_review(): void
    {
        $report = $this->inspector->inspect($this->doc(
            'C&#039;est l&#039;heure',
            "Nouveau paragraphe tapé aujourd'hui, avec &amp; dedans",
        ));

        $this->assertFalse($report->isRepairable());
        $this->assertTrue($report->needsReview());
        $this->assertSame(NoteContentEncodingInspector::REVIEW_MIXED, $report->reviewReason);
    }

    /**
     * Freshly typed text that carries no entity at all is inert: decoding it would be a no op, so it
     * does not stop the encoded node next to it from being repaired.
     */
    public function test_freshly_typed_text_without_entities_does_not_block_the_repair(): void
    {
        $report = $this->inspector->inspect($this->doc(
            'C&#039;est l&#039;heure',
            "Nouveau paragraphe tapé aujourd'hui",
        ));

        $this->assertTrue($report->isRepairable());
        $this->assertSame(
            ["C'est l'heure", "Nouveau paragraphe tapé aujourd'hui"],
            $this->textsOf($report->repairedContent),
        );
    }

    /**
     * Only a member typing `&amp;#039;` produces this, and decoding it once would leave `&#039;`,
     * which the next run would decode again. Refusing it is both the honest answer and what makes
     * the command idempotent by construction.
     */
    public function test_leaves_a_body_still_encoded_after_one_decode_for_review(): void
    {
        $report = $this->inspector->inspect($this->doc(
            'Une apostrophe s&#039;écrit &amp;#039; en HTML',
        ));

        $this->assertFalse($report->isRepairable());
        $this->assertTrue($report->needsReview());
        $this->assertSame(NoteContentEncodingInspector::REVIEW_STILL_ENCODED, $report->reviewReason);
    }

    public function test_walks_nested_nodes_and_leaves_attributes_alone(): void
    {
        $report = $this->inspector->inspect([
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['src' => '/media/repet.jpg?w=800&h=600']],
                [
                    'type' => 'bulletList',
                    'content' => [[
                        'type' => 'listItem',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [[
                                'type' => 'text',
                                'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://salle.fr/?a=1&b=2']]],
                                'text' => 'Réserver l&#039;ampli',
                            ]],
                        ]],
                    ]],
                ],
            ],
        ]);

        $this->assertTrue($report->isRepairable());
        $this->assertSame(["Réserver l'ampli"], $this->textsOf($report->repairedContent));

        $repaired = $report->repairedContent;
        $this->assertSame('/media/repet.jpg?w=800&h=600', $repaired['content'][0]['attrs']['src']);
        $this->assertSame(
            'https://salle.fr/?a=1&b=2',
            $repaired['content'][1]['content'][0]['content'][0]['content'][0]['marks'][0]['attrs']['href'],
        );
    }

    /**
     * The test that pins the rule to the code that actually did the damage.
     *
     * BandSpaceNoteBuilder ran the autowired HtmlSanitizerInterface, which is the `default` sanitizer
     * of config/packages/html_sanitizer.yaml: allow_safe_elements and allow_static_elements both off,
     * which is a bare HtmlSanitizerConfig. Feeding it the strings a French band actually writes and
     * asserting the inspector gives them back byte for byte proves the inverse is the real inverse,
     * not a table that merely looks like one.
     */
    public function test_repair_inverts_the_sanitizer_that_caused_the_damage(): void
    {
        $sanitizer = new HtmlSanitizer(new HtmlSanitizerConfig());

        $original = [
            "C'est l'heure de la répét",
            'Écrire à contact@salle.fr avant le 12/03',
            '2+2=4 et on est prêts à 100 %',
            'On dit "oui" à la date',
            '`intro` puis solo, déjà répété',
            'Rock & Roll',
            'a < b > c',
            'Balances « à 18h », ça marche ?',
        ];

        $corrupted = $this->doc(...array_map(
            static fn(string $text): string => $sanitizer->sanitize($text),
            $original,
        ));

        $report = $this->inspector->inspect($corrupted);

        $this->assertTrue($report->isRepairable());
        $this->assertSame($original, $this->textsOf($report->repairedContent));

        // And why the inferred bucket is repaired rather than held back: a real French note carries
        // `Rock & Roll` and `a < b > c` alongside its apostrophes, and neither comes back with a
        // fingerprint of its own. Refusing every body that holds one would leave most of the damage
        // in place; refusing only those nodes would leave the body half repaired.
        $this->assertTrue($report->isInferred());
        $this->assertSame(
            [false, false, false, false, false, true, true],
            array_column($report->changes, 'inferred'),
        );
    }

    /**
     * The sanitizer is a fixed point on its own output, so opening a broken note again and again
     * never stacked a second encoding. The repair therefore decodes once, and that is the whole fix.
     */
    public function test_reopening_a_broken_note_never_stacked_a_second_encoding(): void
    {
        $sanitizer = new HtmlSanitizer(new HtmlSanitizerConfig());

        $once = $sanitizer->sanitize("C'est l'heure & c'est parti");
        $twice = $sanitizer->sanitize($once);
        $thrice = $sanitizer->sanitize($twice);

        $this->assertSame($once, $twice);
        $this->assertSame($once, $thrice);

        $report = $this->inspector->inspect($this->doc($thrice));

        $this->assertTrue($report->isRepairable());
        $this->assertSame(["C'est l'heure & c'est parti"], $this->textsOf($report->repairedContent));
    }

    /**
     * @return array<string, mixed>
     */
    private function doc(string ...$texts): array
    {
        return [
            'type' => 'doc',
            'content' => array_map(
                static fn(string $text): array => [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $text]],
                ],
                $texts,
            ),
        ];
    }

    /**
     * @param array<string, mixed>|null $content
     * @return list<string>
     */
    private function textsOf(?array $content): array
    {
        $texts = [];
        $this->collect($content ?? [], $texts);

        return $texts;
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string> $texts
     */
    private function collect(array $node, array &$texts): void
    {
        if (isset($node['text']) && is_string($node['text'])) {
            $texts[] = $node['text'];
        }

        if (!isset($node['content']) || !is_array($node['content'])) {
            return;
        }

        foreach ($node['content'] as $child) {
            if (is_array($child)) {
                $this->collect($child, $texts);
            }
        }
    }
}
