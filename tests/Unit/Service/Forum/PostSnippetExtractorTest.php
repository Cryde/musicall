<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Forum;

use App\Service\Forum\PostSnippetExtractor;
use PHPUnit\Framework\TestCase;

class PostSnippetExtractorTest extends TestCase
{
    public function test_match_in_middle_returns_window_with_both_ellipses(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = str_repeat('Lorem ipsum dolor sit amet. ', 20) . 'GUITARE Solo here. ' . str_repeat('More text. ', 20);

        $snippet = $extractor->extract($content, 'guitare');

        $this->assertStringStartsWith('…', $snippet);
        $this->assertStringEndsWith('…', $snippet);
        $this->assertStringContainsString('GUITARE', $snippet);
    }

    public function test_match_at_start_has_no_prefix_ellipsis(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = 'GUITARE solo at the very start of this rather lengthy post body that keeps going and going.';

        $snippet = $extractor->extract($content, 'guitare');

        $this->assertStringStartsNotWith('…', $snippet);
        $this->assertStringContainsString('GUITARE', $snippet);
    }

    public function test_no_match_returns_leading_window(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = str_repeat('Bonjour tout le monde. ', 30);

        $snippet = $extractor->extract($content, 'inexistant');

        $this->assertStringStartsWith('Bonjour', $snippet);
        $this->assertStringEndsWith('…', $snippet);
    }

    public function test_short_content_returns_full_text_without_ellipsis(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = 'Une réponse courte.';

        $snippet = $extractor->extract($content, 'réponse');

        $this->assertSame('Une réponse courte.', $snippet);
    }

    public function test_html_tags_are_stripped(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = '<p>Voici une <strong>guitare</strong> électrique très puissante.</p>';

        $snippet = $extractor->extract($content, 'guitare');

        $this->assertStringNotContainsString('<strong>', $snippet);
        $this->assertStringNotContainsString('<p>', $snippet);
        $this->assertStringContainsString('guitare', $snippet);
    }

    public function test_case_insensitive_match(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = 'Préparer le concert avec la nouvelle Stratocaster acquise hier soir.';

        $snippet = $extractor->extract($content, 'STRATOCASTER');

        $this->assertStringContainsString('Stratocaster', $snippet);
    }

    public function test_multi_term_picks_earliest_match(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = str_repeat('texte de remplissage. ', 10) . 'BASSE puis plus tard GUITARE.';

        $snippet = $extractor->extract($content, 'guitare basse');

        // BASSE appears before GUITARE - the earliest token wins.
        $this->assertStringContainsString('BASSE', $snippet);
    }

    public function test_empty_content_returns_empty_string(): void
    {
        $extractor = new PostSnippetExtractor();
        $this->assertSame('', $extractor->extract('', 'guitare'));
        $this->assertSame('', $extractor->extract('   ', 'guitare'));
    }

    public function test_whitespace_is_collapsed(): void
    {
        $extractor = new PostSnippetExtractor();
        $content = "Premier ligne\n\n\nseconde ligne\t\t\tavec guitare ici.";

        $snippet = $extractor->extract($content, 'guitare');

        $this->assertStringNotContainsString("\n\n", $snippet);
        $this->assertStringNotContainsString("\t", $snippet);
        $this->assertStringContainsString('guitare', $snippet);
    }
}
