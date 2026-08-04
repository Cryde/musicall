<?php

declare(strict_types=1);

namespace App\Command\Pdf;

use App\Enum\BandSpace\TechRiderColour;
use App\Enum\BandSpace\TechRiderStagePlotIcon;
use Sensiolabs\GotenbergBundle\Builder\BuilderInterface;
use Sensiolabs\GotenbergBundle\Builder\Pdf\HtmlPdfBuilder;
use Sensiolabs\GotenbergBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Sensiolabs\GotenbergBundle\Enumeration\PaperSize;
use Sensiolabs\GotenbergBundle\Enumeration\PdfFormat;
use Sensiolabs\GotenbergBundle\Enumeration\ScreenshotFormat;
use Sensiolabs\GotenbergBundle\Enumeration\Unit;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\GotenbergScreenshotInterface;
use Sensiolabs\GotenbergBundle\Processor\FileProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Throwaway probe for #741: is Gotenberg (headless Chromium behind an HTTP API) worth adopting in
 * place of dompdf? Delete this file, config/packages/sensiolabs_gotenberg.yaml, the bundles.php
 * entry, the compose service, GOTENBERG_DSN and the composer dependency together if the answer
 * turns out to be no.
 *
 * It renders through sensiolabs/gotenberg-bundle rather than posting multipart by hand, because the
 * probe has to answer "how much work is the integration" as well as "does Chromium render this",
 * and hand rolled transport code would answer neither. The document itself is built here as a
 * string and passed through contentRaw(), so the spike still adds no template to remove later.
 *
 * What it renders is a miniature tech rider rather than a CSS showcase, because that is the hardest
 * document we already know we want to export and the one the module was shaped around (#769, #774).
 * In one page set it asks for:
 *
 * - a cover page with a gradient and a bitmap logo, so printBackground and image assets are proven
 * - a 44 row patch list that spills onto a second page, so the repeated table head is proven
 * - a stage plot placing icons at fractional coordinates, rotated in quarter turns and scaled,
 *   which is exactly the model #769 stores and the thing dompdf cannot draw at all
 * - flexbox, CSS grid, aspect-ratio, custom properties, counters and break-inside, none of which
 *   dompdf supports and all of which the setlist templates currently work around by hand
 * - a bundled TTF through @font-face, the bug class that produced a production 500 under dompdf
 * - a line written by JavaScript, so the wait-for-expression path is proven for a future chart
 *
 * There is deliberately no --url option: the target is always GOTENBERG_DSN, so running this in
 * production exercises the configured client rather than an ad hoc address, which is the thing that
 * actually needs proving there.
 */
#[AsCommand(
    name: 'app:pdf:gotenberg-probe',
    description: 'Render a deliberately hostile test document through Gotenberg, as PDF and as image, to evaluate it against dompdf (#741)',
)]
class GotenbergProbeCommand extends Command
{
    /** Under var/, which is gitignored, so a probe run leaves nothing to clean up in git. */
    private const string DEFAULT_OUTPUT_DIR = 'var/gotenberg-probe';

    /** A4 with room in the vertical margin for the header and the footer, which print into it. */
    private const float MARGIN_VERTICAL_INCHES = 0.7;
    private const float MARGIN_HORIZONTAL_INCHES = 0.5;

    /** Set by the inline script, so a render that did not wait for scripting cannot pass. */
    private const string READY_EXPRESSION = "document.documentElement.dataset.probeReady === 'true'";

    /** Matches BASE_ICON_PERCENT in assets/js/constants/stagePlot.js, so the plot renders to scale. */
    private const float BASE_ICON_PERCENT = 6.0;

    /**
     * Each case is one question we need answered before choosing a renderer, so a failure is a
     * result rather than an abort: they run independently and every outcome is reported.
     *
     * @var array<string, string>
     */
    private const array CASES = [
        'pdf' => 'Does the hardest document we plan to export render at all?',
        'scaled' => 'Is shrink to fit one call, instead of dompdf\'s measure and re-render loop?',
        'single-page' => 'Can the whole document collapse onto one tall page, which dompdf cannot do?',
        'pdfa' => 'Is an archival PDF/A-2b, for a future contract or invoice, one call?',
        'screenshot-png' => 'Can we get a preview image out of the same HTML, for a thumbnail?',
        'screenshot-jpeg' => 'Does format plus quality get that preview small enough to store?',
    ];

    /**
     * A real 44 channel input list, as [channel, source, microphone, routing, colour]. Static
     * sample data, written in French with the accents and the stage terms a band actually uses,
     * because typography and accents are part of what is being judged here.
     *
     * It is 44 rows rather than a rounder 30 so the table genuinely runs past the foot of the page.
     * At 30 it fitted on one, and the repeated table head, which is the reason a long patch list is
     * in this probe at all, was never exercised.
     *
     * @var list<array{int, string, string, string, TechRiderColour}>
     */
    private const array PATCH_INPUTS = [
        [1, 'Kick In', 'Beta 91A', 'Sub / Batterie', TechRiderColour::Red],
        [2, 'Kick Out', 'D112', 'Sub / Batterie', TechRiderColour::Red],
        [3, 'Caisse claire dessus', 'SM57', 'Batterie', TechRiderColour::Red],
        [4, 'Caisse claire dessous', 'e604', 'Batterie', TechRiderColour::Red],
        [5, 'Charleston', 'KM184', 'Batterie', TechRiderColour::Orange],
        [6, 'Tom aigu', 'e904', 'Batterie', TechRiderColour::Red],
        [7, 'Tom basse', 'e904', 'Batterie', TechRiderColour::Red],
        [8, 'Overhead jardin', 'KM184', 'Batterie', TechRiderColour::Orange],
        [9, 'Overhead cour', 'KM184', 'Batterie', TechRiderColour::Orange],
        [10, 'Ride', 'KM184', 'Batterie', TechRiderColour::Orange],
        [11, 'Basse DI', 'DI active', 'Basse', TechRiderColour::Yellow],
        [12, 'Basse ampli', 'MD421', 'Basse', TechRiderColour::Yellow],
        [13, 'Guitare jardin', 'SM57', 'Guitares', TechRiderColour::Green],
        [14, 'Guitare cour', 'e906', 'Guitares', TechRiderColour::Green],
        [15, 'Guitare acoustique', 'DI passive', 'Guitares', TechRiderColour::Green],
        [16, 'Clavier gauche', 'DI stéréo', 'Claviers', TechRiderColour::Cyan],
        [17, 'Clavier droite', 'DI stéréo', 'Claviers', TechRiderColour::Cyan],
        [18, 'Synthé basse', 'DI active', 'Claviers', TechRiderColour::Cyan],
        [19, 'Sampler gauche', 'DI stéréo', 'Claviers', TechRiderColour::Cyan],
        [20, 'Sampler droite', 'DI stéréo', 'Claviers', TechRiderColour::Cyan],
        [21, 'Chant lead', 'Beta 58A', 'Voix', TechRiderColour::Purple],
        [22, 'Chant jardin', 'SM58', 'Voix', TechRiderColour::Purple],
        [23, 'Chant cour', 'SM58', 'Voix', TechRiderColour::Purple],
        [24, 'Chœurs batteur', 'Beta 58A', 'Voix', TechRiderColour::Purple],
        [25, 'Talkback régie', 'SM58', 'Service', TechRiderColour::Grey],
        [26, 'Playback gauche', 'XLR régie', 'Playback', TechRiderColour::Grey],
        [27, 'Playback droite', 'XLR régie', 'Playback', TechRiderColour::Grey],
        [28, 'Click batteur', 'XLR régie', 'Playback', TechRiderColour::Grey],
        [29, 'Ambiance jardin', 'KM184', 'Ambiance', TechRiderColour::Grey],
        [30, 'Ambiance cour', 'KM184', 'Ambiance', TechRiderColour::Grey],
        [31, 'Tom médium', 'e904', 'Batterie', TechRiderColour::Red],
        [32, 'Splash', 'KM184', 'Batterie', TechRiderColour::Orange],
        [33, 'Cajón', 'Beta 91A', 'Percussions', TechRiderColour::Orange],
        [34, 'Congas', 'KM184', 'Percussions', TechRiderColour::Orange],
        [35, 'Guitare 3 jardin', 'SM57', 'Guitares', TechRiderColour::Green],
        [36, 'Guitare 3 cour', 'e906', 'Guitares', TechRiderColour::Green],
        [37, 'Violon', 'DI active', 'Cordes', TechRiderColour::Yellow],
        [38, 'Saxophone', 'e908', 'Cuivres', TechRiderColour::Purple],
        [39, 'Trompette', 'e908', 'Cuivres', TechRiderColour::Purple],
        [40, 'Trombone', 'e908', 'Cuivres', TechRiderColour::Purple],
        [41, 'Chant 4', 'SM58', 'Voix', TechRiderColour::Purple],
        [42, 'Chœurs claviériste', 'Beta 58A', 'Voix', TechRiderColour::Purple],
        [43, 'HF secours 1', 'Beta 58A', 'Voix', TechRiderColour::Grey],
        [44, 'HF secours 2', 'Beta 58A', 'Voix', TechRiderColour::Grey],
    ];

    /**
     * @var list<array{int, string, string, TechRiderColour}>
     */
    private const array PATCH_OUTPUTS = [
        [1, 'Retour chant lead', 'Wedge', TechRiderColour::Purple],
        [2, 'Retour guitare jardin', 'Wedge', TechRiderColour::Green],
        [3, 'Retour basse', 'Wedge', TechRiderColour::Yellow],
        [4, 'Retour batteur', 'Ears', TechRiderColour::Red],
        [5, 'Façade gauche', 'Ligne', TechRiderColour::Grey],
        [6, 'Façade droite', 'Ligne', TechRiderColour::Grey],
    ];

    /**
     * The stage plot, as [icon, x, y, rotation, scale, label]. Coordinates are fractions of the
     * stage box in [0, 1] and rotations are quarter turns, which is the shape #769 stores, so this
     * is the real document model rather than a drawing that happens to resemble one.
     *
     * @var list<array{TechRiderStagePlotIcon, float, float, int, float, string}>
     */
    private const array STAGE_ELEMENTS = [
        [TechRiderStagePlotIcon::DrumStool, 0.5, 0.16, 0, 0.9, ''],
        [TechRiderStagePlotIcon::DrumKit, 0.5, 0.29, 0, 1.8, 'Batterie'],
        [TechRiderStagePlotIcon::GuitarAmp, 0.22, 0.32, 90, 1.2, 'Ampli guitare'],
        [TechRiderStagePlotIcon::BassAmp, 0.78, 0.32, 270, 1.2, 'Ampli basse'],
        [TechRiderStagePlotIcon::PowerSocket, 0.07, 0.18, 0, 0.8, '230V'],
        [TechRiderStagePlotIcon::DiBox, 0.13, 0.58, 0, 0.8, 'DI basse'],
        [TechRiderStagePlotIcon::Keyboard, 0.81, 0.6, 180, 1.4, 'Clavier'],
        [TechRiderStagePlotIcon::MusicStand, 0.68, 0.72, 0, 0.9, 'Pupitre'],
        [TechRiderStagePlotIcon::PersonMarker, 0.5, 0.72, 0, 1.0, ''],
        [TechRiderStagePlotIcon::VocalMic, 0.5, 0.8, 0, 1.0, 'Chant lead'],
        [TechRiderStagePlotIcon::WedgeMonitor, 0.33, 0.92, 180, 1.1, 'Retour 1'],
        [TechRiderStagePlotIcon::WedgeMonitor, 0.67, 0.92, 180, 1.1, 'Retour 2'],
    ];

    /**
     * @var list<array{string, string}>
     */
    private const array SPEC_CARDS = [
        ['Façade', '2 x 3 kW minimum, couverture homogène, système accroché de préférence.'],
        ['Console', '48 entrées, 8 sorties auxiliaires, égaliseur paramétrique par voie.'],
        ['Retours', '4 wedges sur 3 circuits distincts, plus un circuit ears pour le batteur.'],
        ['Lumière', '8 découpes, 12 PAR LED, 2 stroboscopes, pupitre à mémoires.'],
        ['Loges', 'Une loge fermée pour 5 personnes, miroir, serviettes, accès à un point d\'eau.'],
        ['Catering', '5 repas chauds sans viande, eau plate à température ambiante.'],
    ];

    public function __construct(
        private readonly GotenbergPdfInterface $gotenbergPdf,
        private readonly GotenbergScreenshotInterface $gotenbergScreenshot,
        // The same scoped client the builders use, so /health proves the configured route and not
        // just that something is listening somewhere.
        #[Autowire(service: 'gotenberg.client')]
        private readonly HttpClientInterface $gotenbergClient,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire(env: 'GOTENBERG_DSN')]
        private readonly string $gotenbergDsn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Directory the rendered files are written to, absolute or relative to the project root',
                self::DEFAULT_OUTPUT_DIR,
            )
            ->addOption(
                'case',
                'c',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                sprintf('Run only these cases, repeatable (%s)', implode(', ', array_keys(self::CASES))),
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Gotenberg probe (#741)');

        try {
            $cases = $this->selectCases($input);
            $outputDir = $this->resolveOutputDir((string) $input->getOption('output'));
            $assets = $this->resolveAssets();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$this->reportServer($io, $outputDir)) {
            return Command::FAILURE;
        }

        $html = $this->buildIndexHtml();
        // Written out so the same document can be opened in Chrome and compared. The assets stay
        // where they are and are referenced from there, which is why the copy is a sibling.
        $this->writeSources($html, $assets, $outputDir);
        $io->text(sprintf('Wrote <info>index.html</info> and its assets to <info>%s</info>: open it in Chrome to compare.', $outputDir));
        $io->newLine();

        $rows = [];
        $failures = 0;

        foreach ($cases as $name => $question) {
            $io->text(sprintf('<comment>%s</comment>: %s', $name, $question));

            $startedAt = microtime(true);

            try {
                $file = $this->render($name, $html, $assets, $outputDir);
                $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
                $detail = sprintf('%s, %s, %d ms', $file->getFilename(), $this->humanBytes((int) $file->getSize()), $elapsedMs);
                $io->text(sprintf('  <info>ok</info> %s', $detail));
                $rows[] = [$name, 'ok', $detail];
            } catch (\Throwable $e) {
                ++$failures;
                $detail = $this->firstLine($e->getMessage());
                $io->text(sprintf('  <error>failed</error> %s', $detail));
                $rows[] = [$name, 'failed', $detail];
            }
        }

        $io->newLine();
        $io->table(['Case', 'Result', 'Output'], $rows);

        $this->reportWhatToLookFor($io, $outputDir);

        if ($failures > 0) {
            $io->warning(sprintf('%d of %d case(s) failed, which is itself a result: note which ones.', $failures, count($cases)));

            return Command::FAILURE;
        }

        $io->success('Every case rendered.');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $assets
     */
    private function render(string $name, string $html, array $assets, string $outputDir): \SplFileInfo
    {
        // FileProcessor declares itself ProcessorInterface<\SplFileInfo>, but HtmlPdfBuilder extends
        // AbstractBuilder without an @extends annotation, so the processor generic never reaches it
        // and PHPStan resolves process() to the default NullProcessor's null. It cannot be expressed
        // any better from here, because the builder is not generic to parameterise. Another point
        // for the write up: the bundle's static types stop at its own edge.
        /** @var \SplFileInfo $file */
        $file = match ($name) {
            'pdf' => $this->pdfBuilder($html, $assets, 'rider', $outputDir)
                // A PDF outline out of the heading structure, which dompdf does not produce.
                ->generateDocumentOutline()
                ->waitForExpression(self::READY_EXPRESSION)
                // A template that throws should fail loudly rather than ship a half rendered page.
                ->failOnConsoleExceptions()
                ->generate()
                ->process(),
            'scaled' => $this->pdfBuilder($html, $assets, 'rider-scaled', $outputDir)
                ->scale(0.7)
                ->generate()
                ->process(),
            'single-page' => $this->pdfBuilder($html, $assets, 'rider-single-page', $outputDir)
                ->singlePage()
                ->generate()
                ->process(),
            'pdfa' => $this->pdfBuilder($html, $assets, 'rider-pdfa', $outputDir)
                ->pdfFormat(PdfFormat::Pdf2b)
                ->generate()
                ->process(),
            'screenshot-png' => $this->screenshotBuilder($html, $assets, 'rider', $outputDir)
                ->format(ScreenshotFormat::Png)
                ->generate()
                ->process(),
            'screenshot-jpeg' => $this->screenshotBuilder($html, $assets, 'rider', $outputDir)
                ->format(ScreenshotFormat::Jpeg)
                ->quality(80)
                ->generate()
                ->process(),
            default => throw new \RuntimeException(sprintf('Case %s has no renderer.', $name)),
        };

        return $file;
    }

    /**
     * Returns the marker interface rather than HtmlPdfBuilder, which is what the bundle itself
     * declares and not an oversight: in dev every builder is wrapped in a TraceableBuilder for the
     * profiler panel, which proxies the option methods through __call. A concrete return type here
     * type errors on the first call. The docblock is what keeps PHPStan checking the chain.
     *
     * @param list<string> $assets
     *
     * @return HtmlPdfBuilder
     */
    private function pdfBuilder(string $html, array $assets, string $fileName, string $outputDir): BuilderInterface
    {
        return $this->gotenbergPdf->html()
            ->contentRaw($html)
            ->assets(...$assets)
            ->headerRaw($this->buildHeaderHtml())
            ->footerRaw($this->buildFooterHtml())
            ->paperStandardSize(PaperSize::A4)
            ->margins(
                self::MARGIN_VERTICAL_INCHES,
                self::MARGIN_VERTICAL_INCHES,
                self::MARGIN_HORIZONTAL_INCHES,
                self::MARGIN_HORIZONTAL_INCHES,
                Unit::Inches,
            )
            ->printBackground()
            // Gotenberg appends the extension itself, so this name carries none.
            ->fileName($fileName)
            ->processor(new FileProcessor(new Filesystem(), $outputDir));
    }

    /**
     * No header or footer here: they only exist in a paginated document. The width is a desktop
     * viewport and the height is left at its default, because clip defaults to false and the
     * screenshot then runs the full height of the page.
     *
     * @param list<string> $assets
     *
     * @return HtmlScreenshotBuilder
     */
    private function screenshotBuilder(string $html, array $assets, string $fileName, string $outputDir): BuilderInterface
    {
        return $this->gotenbergScreenshot->html()
            ->contentRaw($html)
            ->assets(...$assets)
            ->width(1240)
            ->fileName($fileName)
            ->processor(new FileProcessor(new Filesystem(), $outputDir));
    }

    /**
     * @return array<string, string>
     */
    private function selectCases(InputInterface $input): array
    {
        $requested = array_map('strval', (array) $input->getOption('case'));
        if ($requested === []) {
            return self::CASES;
        }

        $unknown = array_diff($requested, array_keys(self::CASES));
        if ($unknown !== []) {
            throw new \RuntimeException(sprintf(
                'Unknown case(s) %s. Available: %s.',
                implode(', ', $unknown),
                implode(', ', array_keys(self::CASES)),
            ));
        }

        return array_intersect_key(self::CASES, array_flip($requested));
    }

    private function resolveOutputDir(string $requested): string
    {
        if (trim($requested) === '') {
            throw new \RuntimeException('Option --output cannot be empty.');
        }

        $absolute = str_starts_with($requested, '/') ? $requested : $this->projectDir . '/' . $requested;
        // Trailing slashes go, but not the root's only character, which would leave an empty path
        // and an error message naming nothing.
        $path = $absolute === '/' ? $absolute : rtrim($absolute, '/');

        if (!is_dir($path) && !@mkdir($path, 0775, recursive: true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Cannot create the output directory %s.', $path));
        }

        if (!is_writable($path)) {
            throw new \RuntimeException(sprintf('The output directory %s is not writable.', $path));
        }

        return $path;
    }

    /**
     * Absolute paths, so nothing depends on the bundle's assets_directory setting. Gotenberg puts
     * every upload in one flat directory under its basename, which is why the document refers to
     * each of these by bare filename with no path.
     *
     * @return list<string>
     */
    private function resolveAssets(): array
    {
        $relativePaths = [
            'assets/image/logo.png',
            'assets/fonts/pdf/Inter-Regular.ttf',
            'assets/fonts/pdf/Inter-Bold.ttf',
        ];

        // Derived from the plot itself, so there is no second list of icons to keep in step.
        foreach (self::STAGE_ELEMENTS as [$icon]) {
            $relativePaths[] = 'public/' . TechRiderStagePlotIcon::IMAGE_DIRECTORY . '/' . $icon->value . '.png';
        }

        $absolutePaths = [];
        foreach (array_unique($relativePaths) as $relativePath) {
            $absolutePath = $this->projectDir . '/' . $relativePath;
            if (!is_file($absolutePath)) {
                throw new \RuntimeException(sprintf('Cannot read %s, which the probe document needs.', $relativePath));
            }
            $absolutePaths[] = $absolutePath;
        }

        return $absolutePaths;
    }

    /**
     * @param list<string> $assets
     */
    private function writeSources(string $html, array $assets, string $outputDir): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile($outputDir . '/index.html', $html);
        $filesystem->dumpFile($outputDir . '/header.html', $this->buildHeaderHtml());
        $filesystem->dumpFile($outputDir . '/footer.html', $this->buildFooterHtml());

        foreach ($assets as $asset) {
            $filesystem->copy($asset, $outputDir . '/' . basename($asset), overwriteNewerFiles: true);
        }
    }

    /**
     * Reachability first and loudly, because it is the single thing most likely to differ between
     * dev and prod. /debug is dumped next to the renders so the two can be diffed.
     */
    private function reportServer(SymfonyStyle $io, string $outputDir): bool
    {
        try {
            $health = $this->gotenbergClient->request('GET', '/health');
            $status = $health->getStatusCode();
            $body = $health->getContent(false);
        } catch (\Throwable $e) {
            $io->error([
                sprintf('Gotenberg is unreachable at %s', $this->gotenbergDsn),
                $e->getMessage(),
                'In dev: docker compose up -d gotenberg. Elsewhere, check GOTENBERG_DSN.',
            ]);

            return false;
        }

        $payload = json_decode($body, true);
        $state = \is_array($payload) && \is_string($payload['status'] ?? null) ? $payload['status'] : 'unknown';

        $io->definitionList(
            ['Instance' => $this->gotenbergDsn],
            ['Version' => $this->fetchText('/version') ?? 'unknown'],
            ['Health' => sprintf('HTTP %d, status %s', $status, $state)],
        );

        if ($status !== 200) {
            $io->error(sprintf('Gotenberg answered but is not healthy: %s', $this->firstLine($body)));

            return false;
        }

        $debug = $this->fetchText('/debug');
        if ($debug !== null) {
            (new Filesystem())->dumpFile($outputDir . '/debug.json', $debug);
            $io->text('Wrote <info>debug.json</info>: diff it between dev and prod to catch a configuration difference.');
        }

        return true;
    }

    private function reportWhatToLookFor(SymfonyStyle $io, string $outputDir): void
    {
        // The row count is read off the data rather than written out, because it is the number the
        // document prints and a stale copy of it here reads as a failed render.
        $channelCount = count(self::PATCH_INPUTS);

        $io->section('What to look for');
        $io->listing([
            'rider.pdf page 1: the gradient and the logo printed, and the accents right (Fatigués, Tournée, COMPTÉS). The body font must be Inter and not a system fallback, which settles the @font-face question and the dompdf font cache bug class in one look.',
            sprintf('rider.pdf page 1: "Canaux comptés par JavaScript: %d". Any other value means the render did not wait for scripting.', $channelCount),
            sprintf('rider.pdf: the %d row patch list runs onto a second page, and its column heads come back at the top of it. The colour chips match the palette.', $channelCount),
            'rider.pdf: the stage plot icons sit where the fractions put them, the two amplis are each turned a quarter turn, the clavier is upside down and every label stays level. This is the part dompdf cannot draw.',
            'rider.pdf footer: "Fiche technique MusicAll" at the left and "Page x sur y" at the right, spread apart rather than jammed together. The header carries the band name, because Chromium fills that in from the document title, and it prints on every page including the cover, which Chromium gives no clean way to suppress. Its date comes out US formatted because it is Chromium\'s and not ours.',
            'rider.pdf: the emoji beside the contact line, which needs an emoji font inside the container rather than in our assets.',
            'rider-scaled.pdf against rider.pdf: the same document at 70%, from one call. Compare with the FIT_SCALES loop in SetlistPdfRenderer.',
            'rider.png against rider.jpeg: note the two file sizes, and that the screenshots show the screen variant of the intro block while the PDFs show the print variant.',
            sprintf('index.html in Chrome, printed to PDF from the browser, against rider.pdf: if they agree, a template can be authored in a browser. The whole bundle is in %s.', $outputDir),
            'debug.json if the run reported writing one, for what this instance actually has enabled. The profiler panel the bundle ships collects web requests, so it will not show this run: that is worth a look only once a controller renders something.',
        ]);
    }

    private function buildIndexHtml(): string
    {
        $generatedAt = (new \DateTimeImmutable())->format('d/m/Y à H:i');

        return <<<HTML
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="utf-8">
                <title>Fiche technique - Les Amplis Fatigués</title>
                <style>{$this->buildStylesheet()}</style>
            </head>
            <body>
            <section class="cover">
                <div class="cover-band">
                    <img class="cover-logo" src="logo.png" alt="MusicAll">
                    <p class="cover-kicker">Fiche technique</p>
                    <h1 class="cover-title">Les Amplis Fatigués</h1>
                    <p class="cover-subtitle">Tournée 2026, formation à cinq musiciens</p>
                    <p class="intro screen-only">Vous lisez la version écran de ce bloc.</p>
                    <p class="intro print-only">Vous lisez la version imprimée de ce bloc.</p>
                </div>
                <dl class="cover-meta">
                    <div><dt>Canaux comptés par JavaScript</dt><dd><span id="js-channel-count">JavaScript n'a pas tourné</span></dd></div>
                    <div><dt>Régie</dt><dd>🎸 regie@example.test</dd></div>
                    <div><dt>Document généré le</dt><dd>{$generatedAt}</dd></div>
                </dl>
            </section>

            <section class="sheet">
                <h2>Patch list, entrées</h2>
                <table class="patch">
                    <thead>
                        <tr><th class="num">Ch.</th><th>Source</th><th>Micro ou boîtier</th><th>Routing</th><th class="num">Couleur</th></tr>
                    </thead>
                    <tbody>
                        {$this->buildInputRows()}
                    </tbody>
                </table>

                <div class="avoid-break">
                    <h2>Patch list, sorties</h2>
                    <table class="patch">
                        <thead>
                            <tr><th class="num">Ch.</th><th>Destination</th><th>Type</th><th class="num">Couleur</th></tr>
                        </thead>
                        <tbody>
                            {$this->buildOutputRows()}
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="sheet">
                <h2>Plan de scène</h2>
                <div class="stage">
                    <div class="stage-grid"></div>
                    {$this->buildStageElements()}
                    <p class="stage-front">Face public</p>
                </div>
                <ul class="legend">
                    {$this->buildLegend()}
                </ul>
            </section>

            <section class="sheet">
                <h2>Spécifications</h2>
                <div class="specs">
                    {$this->buildSpecCards()}
                </div>
                <h2>Conditions d'accueil</h2>
                <p class="prose">
                    L'accès au plateau doit être dégagé une heure avant la balance, et le montage de la
                    batterie se fait sur un praticable roulant fourni par l'organisateur. Le groupe
                    voyage avec ses propres câbles jardin et cour, ses boîtiers de direct et ses pieds
                    de micro, mais compte sur la salle pour la façade, les retours et l'alimentation
                    électrique. Toute modification de ce document est communiquée au plus tard la
                    veille de la représentation, et la version faisant foi est celle transmise par
                    courriel avec l'accusé de réception le plus récent.
                </p>
            </section>

            <script>
                document.getElementById('js-channel-count').textContent =
                    String(document.querySelectorAll('[data-channel]').length);
                document.documentElement.dataset.probeReady = 'true';
            </script>
            </body>
            </html>
            HTML;
    }

    private function buildInputRows(): string
    {
        return implode("\n", array_map(
            static fn (array $row): string => sprintf(
                '<tr data-channel="%1$d"><td class="num">%1$d</td><td>%2$s</td><td>%3$s</td><td>%4$s</td>'
                . '<td class="num"><span class="chip" style="background:%5$s"></span>%6$s</td></tr>',
                $row[0],
                $row[1],
                $row[2],
                $row[3],
                $row[4]->hex(),
                $row[4]->label(),
            ),
            self::PATCH_INPUTS,
        ));
    }

    private function buildOutputRows(): string
    {
        return implode("\n", array_map(
            static fn (array $row): string => sprintf(
                '<tr><td class="num">%1$d</td><td>%2$s</td><td>%3$s</td>'
                . '<td class="num"><span class="chip" style="background:%4$s"></span>%5$s</td></tr>',
                $row[0],
                $row[1],
                $row[2],
                $row[3]->hex(),
                $row[3]->label(),
            ),
            self::PATCH_OUTPUTS,
        ));
    }

    /**
     * The rotation goes on the image and not on the wrapper, so the label stays level while the
     * icon turns. Same split as StagePlotCanvas.vue, for the same reason.
     */
    private function buildStageElements(): string
    {
        return implode("\n", array_map(
            static fn (array $element): string => sprintf(
                '<div class="stage-item" style="left:%.4f%%;top:%.4f%%;width:%.4f%%">'
                . '<img src="%s.png" alt="" style="transform:rotate(%ddeg)">%s</div>',
                $element[1] * 100,
                $element[2] * 100,
                self::BASE_ICON_PERCENT * $element[4],
                $element[0]->value,
                $element[3],
                $element[5] === '' ? '' : sprintf('<span class="stage-label">%s</span>', $element[5]),
            ),
            self::STAGE_ELEMENTS,
        ));
    }

    private function buildLegend(): string
    {
        $labelled = array_filter(self::STAGE_ELEMENTS, static fn (array $element): bool => $element[5] !== '');

        return implode("\n", array_map(
            static fn (array $element): string => sprintf(
                '<li><img src="%s.png" alt=""><span>%s</span></li>',
                $element[0]->value,
                $element[5],
            ),
            $labelled,
        ));
    }

    private function buildSpecCards(): string
    {
        return implode("\n", array_map(
            static fn (array $card): string => sprintf(
                '<article class="spec avoid-break"><h3>%s</h3><p>%s</p></article>',
                $card[0],
                $card[1],
            ),
            self::SPEC_CARDS,
        ));
    }

    /**
     * Inlined in a style tag rather than uploaded as a stylesheet, so the written out index.html is
     * one file a browser can open. Everything in here is either something dompdf ignores or
     * something the setlist templates currently work around by hand.
     */
    private function buildStylesheet(): string
    {
        return <<<CSS
            /* Fonts travel with the document, so there is no font cache to write and nothing to
               regenerate per release: the bug that produced a production 500 under dompdf. */
            @font-face {
                font-family: 'Probe Inter';
                src: url('Inter-Regular.ttf') format('truetype');
                font-weight: 400;
            }
            @font-face {
                font-family: 'Probe Inter';
                src: url('Inter-Bold.ttf') format('truetype');
                font-weight: 700;
            }

            /* Size only, for parity when this same file is printed straight from Chrome. The
               margins belong to the builder's margins() call, because the header and the footer
               print into them, so setting them here as well would double count. */
            @page { size: A4; }

            :root {
                --brand: #3a6589;
                --ink: #1f2937;
                --muted: #6b7280;
                --rule: #e5e7eb;
                --wash: #f8fafc;
            }

            * { box-sizing: border-box; }

            html {
                font-family: 'Probe Inter', sans-serif;
                font-size: 9.5pt;
                line-height: 1.45;
                color: var(--ink);
                /* Keeps the gradients and the chips from being flattened to white. */
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            body { margin: 0; counter-reset: section; }

            h2 {
                font-size: 13pt;
                margin: 0 0 3mm;
                padding-bottom: 1.5mm;
                border-bottom: 2px solid var(--brand);
            }
            h2::before {
                counter-increment: section;
                content: counter(section) '. ';
                color: var(--brand);
            }

            /* Cover page: a flex column, so the meta block sits at the foot of the page without
               being positioned by hand. Height in mm rather than vh, which is not worth betting a
               probe on. */
            .cover {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 235mm;
                padding: 12mm;
                border-radius: 4mm;
                color: #fff;
                background: linear-gradient(140deg, #1e3a5f 0%, var(--brand) 55%, #6aa0c8 100%);
            }
            .cover-logo { width: 34mm; margin-bottom: 10mm; }
            .cover-kicker {
                margin: 0;
                font-size: 10pt;
                letter-spacing: 0.4em;
                text-transform: uppercase;
                opacity: 0.75;
            }
            .cover-title { margin: 2mm 0 0; font-size: 34pt; line-height: 1.1; }
            .cover-subtitle { margin: 3mm 0 0; font-size: 12pt; opacity: 0.85; }

            .intro {
                margin: 8mm 0 0;
                padding: 3mm 4mm;
                border-left: 3px solid rgba(255, 255, 255, 0.6);
                background: rgba(255, 255, 255, 0.12);
                border-radius: 0 2mm 2mm 0;
            }
            /* The PDF should show one of these and the screenshot the other, which is print media
               emulation proving itself. */
            .print-only { display: none; }
            @media print {
                .screen-only { display: none; }
                .print-only { display: block; }
            }

            .cover-meta {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 5mm;
                margin: 0;
                padding-top: 6mm;
                border-top: 1px solid rgba(255, 255, 255, 0.35);
            }
            .cover-meta dt { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.75; }
            .cover-meta dd { margin: 1mm 0 0; font-weight: 700; }

            .sheet { break-before: page; padding-top: 2mm; }

            .patch { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
            /* The list runs longer than a page, so the head has to come back at the top of the next. */
            .patch thead { display: table-header-group; }
            .patch th {
                text-align: left;
                font-size: 7.5pt;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: var(--muted);
                padding: 1.5mm 2mm;
                border-bottom: 1px solid var(--rule);
            }
            .patch td { padding: 1.4mm 2mm; border-bottom: 1px solid var(--rule); }
            .patch tbody tr:nth-child(even) { background: var(--wash); }
            .patch .num { text-align: right; white-space: nowrap; }
            .chip {
                display: inline-block;
                width: 2.6mm;
                height: 2.6mm;
                margin-right: 1.5mm;
                border-radius: 50%;
                vertical-align: middle;
            }

            .avoid-break { break-inside: avoid; }

            /* Fractional coordinates need a box of known proportions to resolve against, which is
               what aspect-ratio gives without a spacer hack. */
            .stage {
                position: relative;
                width: 100%;
                aspect-ratio: 16 / 9;
                border: 1px solid var(--rule);
                border-radius: 3mm;
                background: var(--wash);
                overflow: hidden;
            }
            /* A 5% mesh, so a reader can check by eye that the fractions landed where they claim. */
            .stage-grid {
                position: absolute;
                inset: 0;
                background-image:
                    repeating-linear-gradient(to right, rgba(58, 101, 137, 0.14) 0 1px, transparent 1px 5%),
                    repeating-linear-gradient(to bottom, rgba(58, 101, 137, 0.14) 0 1px, transparent 1px 5%);
            }
            .stage-item { position: absolute; transform: translate(-50%, -50%); }
            .stage-item img { display: block; width: 100%; }
            .stage-label {
                position: absolute;
                top: 100%;
                left: 50%;
                transform: translateX(-50%);
                margin-top: 0.8mm;
                font-size: 6pt;
                white-space: nowrap;
                color: var(--muted);
            }
            .stage-front {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                margin: 0;
                padding: 1.2mm 0;
                text-align: center;
                font-size: 7pt;
                letter-spacing: 0.3em;
                text-transform: uppercase;
                color: #fff;
                background: linear-gradient(to top, rgba(31, 41, 55, 0.85), rgba(31, 41, 55, 0.35));
            }

            .legend {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 2mm 4mm;
                margin: 5mm 0 0;
                padding: 0;
                list-style: none;
            }
            .legend li { display: flex; align-items: center; gap: 2mm; font-size: 8pt; }
            .legend img { width: 5mm; }

            .specs {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 4mm;
                margin-bottom: 8mm;
            }
            .spec {
                padding: 4mm;
                border: 1px solid var(--rule);
                border-radius: 2.5mm;
                background: #fff;
                box-shadow: 0 1mm 2mm rgba(31, 41, 55, 0.06);
            }
            .spec h3 { margin: 0 0 1.5mm; font-size: 9.5pt; color: var(--brand); }
            .spec p { margin: 0; font-size: 8pt; color: var(--muted); }

            .prose { orphans: 3; widows: 3; text-align: justify; margin: 0; }
            CSS;
    }

    /**
     * Header and footer render in their own Chromium context: our stylesheet does not reach them,
     * no uploaded asset loads, and only fonts installed in the image are available. So they are
     * complete documents with their own inline CSS, and Chromium fills the spans in by class name.
     *
     * They lay out with a table and not with flexbox, which is not a style preference. The body in
     * that context is a shrink to fit box, so justify-content: space-between collapses the two ends
     * together against the left margin. A table with width 100% is the layout that holds, and the
     * real integration will need the same. Verified: flexbox first, and it came out jammed.
     */
    private function buildHeaderHtml(): string
    {
        return $this->buildRunningHtml(
            'bottom',
            '<span class="title"></span>',
            // Chromium's own date, so it comes out US formatted whatever the document language says.
            // A French header has to pass its own string instead of using class="date".
            '<span class="date"></span>',
        );
    }

    private function buildFooterHtml(): string
    {
        return $this->buildRunningHtml(
            'top',
            '<span>Fiche technique MusicAll</span>',
            '<span>Page <span class="pageNumber"></span> sur <span class="totalPages"></span></span>',
        );
    }

    private function buildRunningHtml(string $ruleSide, string $left, string $right): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="utf-8">
                <title>MusicAll</title>
                <style>
                    html { font-size: 9px; color: #6b7280; -webkit-print-color-adjust: exact; }
                    /* No body margin. Chromium forces this body to the full page width, so a margin
                       shifts the content right without narrowing it and the right hand end is
                       clipped off the page: "Page 3 sur 5" printed as "Page 3 su". The horizontal
                       inset has to go on the outer cells instead. */
                    body { margin: 0; }
                    table { width: 100%; border-collapse: collapse; border-{$ruleSide}: 1px solid #e5e7eb; }
                    td { padding: 3px 0; }
                    td.left { padding-left: 12mm; }
                    td.right { padding-right: 12mm; text-align: right; }
                </style>
            </head>
            <body>
                <table><tr><td class="left">{$left}</td><td class="right">{$right}</td></tr></table>
            </body>
            </html>
            HTML;
    }

    private function fetchText(string $path): ?string
    {
        try {
            $response = $this->gotenbergClient->request('GET', $path);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return trim($response->getContent());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * strtok returns false on an empty subject, so a returned string is never itself empty. The
     * carriage return goes too: a CRLF error body would otherwise leave a bare \r in the middle of
     * the reported line and move the terminal cursor over what was already printed.
     */
    private function firstLine(string $body): string
    {
        $line = strtok(trim($body), "\n");

        return $line === false ? '(no message)' : mb_substr(rtrim($line, "\r"), 0, 300);
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }

        return sprintf('%.1f MB', $bytes / (1024 * 1024));
    }
}
