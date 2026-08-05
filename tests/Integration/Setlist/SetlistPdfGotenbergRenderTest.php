<?php declare(strict_types=1);

namespace App\Tests\Integration\Setlist;

use App\Enum\BandSpace\SetlistItemType;
use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;
use App\Repository\BandSpace\SetlistRepository;
use App\Service\Setlist\SetlistPdfOptions;
use App\Service\Setlist\SetlistPdfRenderer;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingGotenbergClient;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use Symfony\Component\HttpClient\HttpClient;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The two things a fake Gotenberg can never tell us, checked against a real one.
 *
 * The rest of the suite substitutes the client, which is right: it keeps the tests fast, hermetic and
 * able to assert on what was sent. But neither of the properties below is visible in a request. Only
 * Chromium can say whether the font really got embedded or whether the computed scale really lands
 * the set on one page, and both are exactly the kind of thing that breaks quietly at deploy time.
 *
 * These fail rather than skip under CI. A test that skips when the service is missing reports green
 * on a completely broken renderer, which is worse than not having the test.
 */
#[ResetDatabase]
class SetlistPdfGotenbergRenderTest extends ApiTestCase
{
    private const string HEALTH_TIMEOUT_SECONDS = '3';

    private SetlistPdfRenderer $renderer;

    private SetlistRepository $setlistRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireGotenberg();

        // Same wiring as production, only with the recording client passing calls straight through.
        self::getContainer()->get(RecordingGotenbergClient::class)->passthrough();

        $this->renderer = self::getContainer()->get(SetlistPdfRenderer::class);
        $this->setlistRepository = self::getContainer()->get(SetlistRepository::class);
    }

    /**
     * Guards the bug class this migration reopened. Under dompdf a font that failed to register fell
     * back to Helvetica silently; under Gotenberg the equivalent is an asset that never arrives, and
     * the document still renders, just in the wrong typeface. Nothing short of reading the embedded
     * font table catches it.
     */
    public function test_each_font_is_really_embedded_and_not_silently_replaced(): void
    {
        $setlist = $this->createSetlist('Font check', ['Hello']);
        $entity = $this->setlistRepository->find((string) $setlist->id);

        $lengths = [];
        foreach ([
            [SetlistPdfFont::Inter, 'Inter'],
            [SetlistPdfFont::AtkinsonHyperlegible, 'AtkinsonHyperlegible'],
            [SetlistPdfFont::SourceSerif, 'SourceSerif'],
        ] as [$font, $marker]) {
            $pdf = $this->renderer->render($entity, new SetlistPdfOptions(layout: SetlistPdfLayout::Large, font: $font), 0, 0);

            $this->assertStringStartsWith('%PDF-', $pdf);
            // Chromium writes a subset tag of six capitals before the PostScript name, so an embedded
            // Inter appears as something like "/BaseFont /AAAAAA+Inter-Regular".
            $this->assertMatchesRegularExpression(
                '/\/BaseFont\s*\/[A-Z]{6}\+' . preg_quote($marker, '/') . '/',
                $pdf,
                \sprintf('font=%s must be embedded, not replaced by a system fallback', $font->value),
            );

            $lengths[$font->value] = \strlen($pdf);
        }

        // Sanity: three different typefaces should not produce three identically sized documents.
        $this->assertNotSame($lengths['inter'], $lengths['source_serif']);
    }

    /**
     * The measurement plus the scale, end to end. The hermetic test proves the two requests carry the
     * right fields; only this one proves the resulting number actually fits the page.
     */
    public function test_a_full_compact_set_really_lands_on_one_page_when_fitted(): void
    {
        $titles = array_map(static fn (int $i): string => 'Chanson numéro ' . $i, range(1, 13));

        // Two unbreakable titles, because they are the content shape the safety factor works hardest
        // on. The scale is derived from a measurement taken at the printable width, and is safe
        // because a wider layout can only wrap less; a single long word does not wrap at any width,
        // so it gets none of that headroom. Measured worst case is still an 8% margin, but a fixture
        // of short breakable titles would never have shown it.
        $titles[] = 'Supercalifragilisticexpialidocious';
        $titles[] = 'Anticonstitutionnellementement';

        $setlist = $this->createSetlist('Fit check', $titles);
        $entity = $this->setlistRepository->find((string) $setlist->id);

        $withoutFit = $this->renderer->render($entity, new SetlistPdfOptions(layout: SetlistPdfLayout::Compact), 0, 0);
        $withFit = $this->renderer->render($entity, new SetlistPdfOptions(layout: SetlistPdfLayout::Compact, fitToOnePage: true), 0, 0);

        $this->assertGreaterThan(
            1,
            self::countPages($withoutFit),
            'Fifteen compact songs must overflow more than one page at full size, or this proves nothing',
        );
        $this->assertSame(1, self::countPages($withFit), 'The fitted export must land on exactly one page');
    }

    /**
     * @param list<string> $titles
     */
    private function createSetlist(string $name, array $titles): object
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => $name])->create();

        foreach ($titles as $position => $title) {
            $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => $title])->create();
            SetlistItemFactory::new([
                'setlist' => $setlist,
                'type' => SetlistItemType::Song,
                'song' => $song,
                'label' => null,
                'position' => $position,
            ])->create();
        }

        return $setlist;
    }

    /** Counts page objects without a PDF library. "[^s]" keeps /Pages from matching. */
    private static function countPages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    private function requireGotenberg(): void
    {
        $dsn = $_SERVER['GOTENBERG_DSN'] ?? $_ENV['GOTENBERG_DSN'] ?? null;
        $reason = 'Gotenberg is required for this test. Set GOTENBERG_DSN and start the service.';

        if (!\is_string($dsn) || $dsn === '') {
            $this->skipOrFail($reason);
        }

        try {
            $status = HttpClient::create()
                ->request('GET', rtrim((string) $dsn, '/') . '/health', ['timeout' => (float) self::HEALTH_TIMEOUT_SECONDS])
                ->getStatusCode();
        } catch (\Throwable $e) {
            $this->skipOrFail($reason . ' ' . $e->getMessage());

            return;
        }

        if ($status !== 200) {
            $this->skipOrFail(\sprintf('%s Health returned HTTP %d.', $reason, $status));
        }
    }

    /**
     * Skipping is a convenience for a developer without the container running. In CI it would hide a
     * broken renderer behind a green run, so there it is a failure.
     */
    private function skipOrFail(string $reason): void
    {
        if (($_SERVER['CI'] ?? $_ENV['CI'] ?? false) !== false) {
            self::fail($reason);
        }

        self::markTestSkipped($reason);
    }
}
