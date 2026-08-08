<?php declare(strict_types=1);

namespace App\Tests\Integration\TechRider;

use App\Entity\BandSpace\TechRider;
use App\Enum\BandSpace\TechRiderColour;
use App\Enum\BandSpace\TechRiderItemType;
use App\Repository\BandSpace\TechRiderRepository;
use App\Service\BandSpace\TechRider\TechRiderPdfRenderer;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingGotenbergClient;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The three things a fake Gotenberg can never answer.
 *
 * Everything else about the export is asserted on the request, which is faster and sharper. But
 * whether the font really embedded, whether the merged document really carries the attachment's pages
 * in the composed order, and whether a stage plot really draws are all properties of Chromium's
 * output, and each of them is exactly the kind of thing that breaks quietly.
 *
 * These fail rather than skip under CI, because a test that skips on a missing service reports green
 * on a completely broken renderer.
 */
#[ResetDatabase]
class TechRiderPdfRenderTest extends ApiTestCase
{
    private TechRiderPdfRenderer $renderer;

    private TechRiderRepository $riderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireGotenberg();
        self::getContainer()->get(RecordingGotenbergClient::class)->passthrough();

        $this->renderer = self::getContainer()->get(TechRiderPdfRenderer::class);
        $this->riderRepository = self::getContainer()->get(TechRiderRepository::class);
    }

    /**
     * Under dompdf the equivalent failure was a font that silently fell back to Helvetica. Here it is
     * an asset that never arrives, and the document still renders, just in the wrong typeface.
     */
    public function test_the_bundled_font_is_really_embedded(): void
    {
        [$bandSpace, $rider] = $this->seed();
        $this->addText($rider, 'Accueil', 0, 'Le plateau doit être dégagé une heure avant la balance.');

        $pdf = $this->render($bandSpace, $rider);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertMatchesRegularExpression(
            '/\/BaseFont\s*\/[A-Z]{6}\+Inter/',
            $pdf,
            'Inter must be embedded, not replaced by a system fallback',
        );
    }

    /**
     * The merge, and specifically its **order**.
     *
     * Gotenberg merges in alphabetical order of filename rather than in the order the files are sent.
     * The first version of this named its temp files after their content, so a "Plan de salle.pdf"
     * sorted ahead of the cover and the venue received the attachment first. The page count was right
     * and nothing failed, which is why the assertion below is about which page carries what.
     */
    public function test_an_attachment_is_merged_at_the_position_it_was_composed(): void
    {
        [$bandSpace, $rider, $user] = $this->seed();
        $this->addText($rider, 'Avant', 0, 'Avant la pièce jointe.');
        // Deliberately A5. Page geometry is what identifies the attachment's pages in the merged
        // document, because Chromium subsets its fonts and writes glyph ids rather than text, so the
        // words are not recoverable from the bytes without a PDF toolchain this container lacks.
        $this->attachPdf($user, $bandSpace, $rider, position: 1);
        $this->addText($rider, 'Après', 2, 'Après la pièce jointe.');

        $sizes = $this->pageSizes($this->render($bandSpace, $rider));

        $this->assertCount(4, $sizes, 'Cover, the first run, the attachment, then the last run');
        $this->assertSame(
            ['portrait-a4', 'portrait-a4', 'attachment-a5', 'portrait-a4'],
            $sizes,
            'The attachment must land where it was composed, not first: Gotenberg merges in '
            . 'alphabetical order of filename, so only the ordinal naming keeps this right',
        );
    }

    /** The drawing itself: fractional coordinates, the icon assets, and the audience edge. */
    public function test_a_stage_plot_draws(): void
    {
        [$bandSpace, $rider] = $this->seed();
        TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::StagePlot,
            'title' => 'Plan de scène',
            'position' => 0,
            'content' => [
                'version' => 1,
                'stage' => ['aspect_ratio' => 1.4],
                'elements' => [
                    ['id' => 'a', 'icon' => 'drum_kit', 'x' => 0.5, 'y' => 0.3, 'scale' => 1.8, 'rotation' => 0, 'label' => 'Batterie'],
                    ['id' => 'b', 'icon' => 'guitar_amp', 'x' => 0.22, 'y' => 0.35, 'scale' => 1.2, 'rotation' => 90, 'label' => 'Ampli guitare', 'colour' => TechRiderColour::Green->value],
                ],
                'legend' => [['icon' => 'drum_kit', 'label' => 'Batterie']],
            ],
        ])->create();

        $pdf = $this->render($bandSpace, $rider);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertCount(2, $this->pageSizes($pdf), 'A cover and the plot');
        // The icons are uploaded assets referenced by bare filename, so an embedded image is the
        // proof they resolved. A broken reference renders a page that is silently empty.
        $this->assertMatchesRegularExpression(
            '#/Subtype\s*/Image#',
            $pdf,
            'The stage plot icons must be embedded in the document',
        );
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array{object, object, object}
     */
    private function seed(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'Les Amplis'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Fiche technique'])->create();

        return [$bandSpace, $rider, $user];
    }

    private function addText(object $rider, string $title, int $position, string $body): void
    {
        TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Text,
            'title' => $title,
            'position' => $position,
            'content' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $body]]],
            ]],
        ])->create();
    }

    /** A real one-page A5 PDF, built by Chromium so the merge is fed something it actually produced. */
    private function attachPdf(object $user, object $bandSpace, object $rider, int $position): void
    {
        $bytes = $this->renderStandalonePdf('Pièce jointe');
        $storagePath = 'rider-' . bin2hex(random_bytes(4)) . '.pdf';

        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => 'Plan de salle.pdf',
        ])->create();
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file, 'versionNumber' => 1, 'createdBy' => $user,
            'mimeType' => 'application/pdf', 'size' => strlen($bytes), 'storagePath' => $storagePath,
        ])->create();
        $file->currentVersion = $version;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::getContainer()->get('oneup_flysystem.musicall_filesystem')
            ->write('/band_space_files/' . $bandSpace->id . '/' . $storagePath, $bytes);

        TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Document,
            'title' => 'Plan de salle',
            'position' => $position,
            'file' => $file,
        ])->create();
    }

    private function renderStandalonePdf(string $marker): string
    {
        $html = sprintf('<!DOCTYPE html><html><head><title>a</title></head><body><p>%s</p></body></html>', $marker);
        $boundary = 'b' . bin2hex(random_bytes(8));
        $body = sprintf(
            "--%1\$s\r\nContent-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\n"
            . "Content-Type: text/html\r\n\r\n%2\$s\r\n--%1\$s--\r\n",
            $boundary,
            $html,
        );

        $body = str_replace(
            "--{$boundary}--",
            sprintf(
                "--%1\$s\r\nContent-Disposition: form-data; name=\"paperWidth\"\r\n\r\n5.83\r\n"
                . "--%1\$s\r\nContent-Disposition: form-data; name=\"paperHeight\"\r\n\r\n8.27\r\n--%1\$s--",
                $boundary,
            ),
            $body,
        );

        return HttpClient::create()->request('POST', $this->dsn() . '/forms/chromium/convert/html', [
            'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
            'body' => $body,
        ])->getContent();
    }

    private function render(object $bandSpace, object $rider): string
    {
        $entity = $this->riderRepository->findOneByIdAndBandSpace((string) $rider->id, $bandSpace);
        self::assertInstanceOf(TechRider::class, $entity);

        return $this->renderer->render($entity);
    }

    /**
     * Each page's shape, in page order, named rather than measured so a failure reads as an ordering
     * problem rather than a wall of numbers.
     *
     * The page dictionary is written uncompressed, so the media boxes are readable straight from the
     * bytes, and their order in the file matches page order. Note the two producers space the array
     * differently: Chromium writes `[0 0 ...]` and the merge engine `[ 0 0 ... ]`.
     *
     * @return list<string>
     */
    private function pageSizes(string $pdf): array
    {
        preg_match_all(
            '#/MediaBox\s*\[\s*[\d.+-]+\s+[\d.+-]+\s+([\d.]+)\s+([\d.]+)\s*\]#',
            $pdf,
            $matches,
            PREG_SET_ORDER,
        );

        return array_map(static function (array $match): string {
            $width = (int) round((float) $match[1]);

            return $width > 500 ? 'portrait-a4' : 'attachment-a5';
        }, $matches);
    }

    private function requireGotenberg(): void
    {
        $reason = 'Gotenberg is required for this test. Set GOTENBERG_DSN and start the service.';

        try {
            $status = HttpClient::create()
                ->request('GET', $this->dsn() . '/health', ['timeout' => 3.0])
                ->getStatusCode();
        } catch (\Throwable $e) {
            $this->skipOrFail($reason . ' ' . $e->getMessage());

            return;
        }

        if ($status !== 200) {
            $this->skipOrFail(sprintf('%s Health returned HTTP %d.', $reason, $status));
        }
    }

    private function dsn(): string
    {
        $dsn = $_SERVER['GOTENBERG_DSN'] ?? $_ENV['GOTENBERG_DSN'] ?? '';

        return rtrim(is_string($dsn) ? $dsn : '', '/');
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
