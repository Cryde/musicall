<?php declare(strict_types=1);

namespace App\Service\Setlist;

use App\Entity\BandSpace\Setlist;
use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;
use Sensiolabs\GotenbergBundle\Builder\BuilderFileInterface;
use Sensiolabs\GotenbergBundle\Builder\BuilderInterface;
use Sensiolabs\GotenbergBundle\Builder\Pdf\HtmlPdfBuilder;
use Sensiolabs\GotenbergBundle\Enumeration\PaperSize;
use Sensiolabs\GotenbergBundle\Enumeration\Unit;
use Sensiolabs\GotenbergBundle\Exception\ExceptionInterface as GotenbergException;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\Processor\InMemoryProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Twig\Environment;

/**
 * Renders a setlist to PDF through Gotenberg. Callers depend only on render() returning the bytes.
 *
 * The bytes rather than a streamed response, deliberately: GotenbergFileResult::stream() builds its
 * own Content-Disposition through HeaderUtils::makeDisposition(), which has no ASCII fallback and is
 * exactly the crash #731 fixed for a setlist named "Répétition générale". App\Http\ContentDisposition
 * keeps owning that header.
 */
readonly class SetlistPdfRenderer
{
    /** Relative to the project root. The two TTFs of the chosen family are uploaded from here. */
    private const string FONT_DIRECTORY = 'assets/fonts/pdf';

    /**
     * The page box lives here and nowhere else. It used to be a CSS @page rule, which cannot stay:
     * a CSS margin silently overrides the builder's margin fields, so the fit arithmetic below and
     * the real page would have disagreed with no way to tell which had won.
     */
    private const float PAGE_HEIGHT_MM = 297.0;
    private const float PAGE_WIDTH_MM = 210.0;
    private const float MARGIN_TOP_MM = 18.0;
    private const float MARGIN_BOTTOM_MM = 14.0;
    private const float MARGIN_SIDE_MM = 14.0;

    private const float POINTS_PER_MM = 72 / 25.4;

    /**
     * Above this many items a one-page render would be illegible, so the request is ignored and we
     * fall back to a normal render. Mirrors the frontend cap in PdfExportPopover.vue.
     *
     * This is now purely a legibility policy. It used to also be a technical guard, because the
     * measure-and-re-render loop cost one render per attempt; a Chromium render measures once.
     */
    private const int MAX_FIT_ITEMS = 15;

    /**
     * How small the type may get before a two-page sheet is the better answer. Same floor as the
     * old FIT_SCALES ladder, kept so a fit export cannot become unreadable.
     */
    private const float MIN_FIT_SCALE = 0.42;

    /**
     * Buys back the rounding between what Chromium reports for the content and what it then lays
     * out, so a set measured at exactly the page height does not spill by a millimetre.
     *
     * Kept at 0.98 on evidence rather than nerve. The scale below is safe because a scaled layout is
     * wider, so lines wrap less than they did when measured; content that cannot wrap at all, a
     * single very long word, gets none of that and is therefore the worst case. Measured against a
     * live Chromium, a fifteen row set with unbreakable titles still landed 8% inside the page. A
     * smaller factor would shrink every export to buy margin that measurement says is already there.
     */
    private const float FIT_SAFETY_FACTOR = 0.98;

    public function __construct(
        private Environment $twig,
        private GotenbergPdfInterface $gotenberg,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function render(
        Setlist $setlist,
        SetlistPdfOptions $options,
        int $totalDurationSeconds,
        int $missingDurationItems = 0,
    ): string {
        $font = $options->effectiveFont();
        $scale = $this->resolveFitScale($setlist, $options, $totalDurationSeconds, $missingDurationItems, $font);

        $builder = $this->builder(
            $this->renderHtml($setlist, $options, $totalDurationSeconds, $missingDurationItems, $font),
            $font,
        );

        if ($scale !== null) {
            // Chromium's print scale widens the layout viewport by 1/scale, so the table still
            // spans the full printable width while every length shrinks together. That is why the
            // templates carry no scale arithmetic of their own any more.
            $builder->scale($scale);
        }

        return $this->generate($builder);
    }

    /**
     * The scale that makes the document fit one page, or null to render at full size.
     *
     * A scale cannot be derived from the item count: fifteen rows measure anywhere between 1223pt
     * and 2533pt depending only on how long the titles are, so a count-based formula would either
     * overflow or make every export needlessly small. One measurement pass answers it exactly.
     */
    private function resolveFitScale(
        Setlist $setlist,
        SetlistPdfOptions $options,
        int $totalDurationSeconds,
        int $missingDurationItems,
        SetlistPdfFont $font,
    ): ?float {
        if (!$options->fitToOnePage || $setlist->items->count() > self::MAX_FIT_ITEMS) {
            return null;
        }

        $naturalHeightPt = $this->measureContentHeightPt($setlist, $options, $totalDurationSeconds, $missingDurationItems, $font);
        if ($naturalHeightPt <= 0.0) {
            return null;
        }

        $availableHeightPt = (self::PAGE_HEIGHT_MM - self::MARGIN_TOP_MM - self::MARGIN_BOTTOM_MM) * self::POINTS_PER_MM;
        if ($naturalHeightPt <= $availableHeightPt) {
            // Already fits, so shrinking would only make it smaller for no reason.
            return null;
        }

        return max(
            self::MIN_FIT_SCALE,
            min(1.0, $availableHeightPt / $naturalHeightPt * self::FIT_SAFETY_FACTOR),
        );
    }

    /**
     * Asks Chromium how tall the document really is, by rendering it onto a single page as tall as
     * its content and reading that page's height back.
     *
     * Two details are load bearing. The margins are zeroed, because singlePage reports the content
     * height plus whatever margin is asked for. And the body width is pinned to the printable width,
     * because this pass lays out at Chromium's own screen viewport rather than the paper: left
     * unpinned it under-reports by about a fifth and produces a confident two-page "fit".
     *
     * The result is safe in the one direction that matters. At scale s the layout is 1/s wider, so
     * lines can only wrap less than they did when measured, never more.
     */
    private function measureContentHeightPt(
        Setlist $setlist,
        SetlistPdfOptions $options,
        int $totalDurationSeconds,
        int $missingDurationItems,
        SetlistPdfFont $font,
    ): float {
        $html = $this->renderHtml(
            $setlist,
            $options,
            $totalDurationSeconds,
            $missingDurationItems,
            $font,
            measureWidthMm: self::PAGE_WIDTH_MM - (2 * self::MARGIN_SIDE_MM),
        );

        $measurement = $this->generate(
            $this->builder($html, $font)
                ->singlePage()
                ->margins(0, 0, 0, 0, Unit::Millimeters),
        );

        return $this->readPageHeightPt($measurement);
    }

    /**
     * Chromium writes the page dictionary uncompressed, so the media box is readable without a PDF
     * library. Returning 0 on no match means the caller renders at full size: a fit that could not
     * be measured should degrade to an honest multi-page document, not to an exception.
     */
    private function readPageHeightPt(string $pdf): float
    {
        $pattern = '/\/MediaBox\s*\[\s*[\d.+-]+\s+[\d.+-]+\s+[\d.+-]+\s+([\d.+-]+)\s*\]/';

        return preg_match($pattern, $pdf, $matches) === 1 ? (float) $matches[1] : 0.0;
    }

    /**
     * Returns the marker interface rather than HtmlPdfBuilder on purpose: in dev the bundle wraps
     * every builder in a TraceableBuilder for its profiler, which proxies the option methods through
     * __call, so a concrete return type here type errors on the first call. The docblock is what
     * keeps the fluent chain statically checked.
     *
     * @return HtmlPdfBuilder
     */
    private function builder(string $html, SetlistPdfFont $font): BuilderInterface
    {
        $fontDirectory = $this->projectDir . '/' . self::FONT_DIRECTORY;

        return $this->gotenberg->html()
            ->contentRaw($html)
            // Only the chosen family, two files. dompdf had to register all three on every render.
            ->assets(
                $fontDirectory . '/' . $font->regularFile(),
                $fontDirectory . '/' . $font->boldFile(),
            )
            ->paperStandardSize(PaperSize::A4)
            ->margins(
                self::MARGIN_TOP_MM,
                self::MARGIN_BOTTOM_MM,
                self::MARGIN_SIDE_MM,
                self::MARGIN_SIDE_MM,
                Unit::Millimeters,
            )
            ->printBackground()
            // InMemoryProcessor warns against production use because it holds the whole document in
            // a string. That is the right trade here and changes nothing: the caller already puts
            // the full body into a Response, as dompdf's output() did, and a setlist PDF measures in
            // hundreds of kilobytes. Streaming instead would mean giving up the bytes seam, and with
            // it the Content-Disposition handling that #731 exists for.
            ->processor(new InMemoryProcessor());
    }

    /**
     * A failed render is a dependency failure, not a client mistake, so it becomes a 502 rather than
     * the 500 an uncaught transport error would produce. Only Gotenberg's own failures and transport
     * errors are caught; a Twig or logic error still surfaces as itself.
     */
    private function generate(BuilderFileInterface $builder): string
    {
        try {
            /**
             * InMemoryProcessor is declared ProcessorInterface<string>, but HtmlPdfBuilder extends
             * AbstractBuilder without an @extends annotation, so the processor generic never reaches
             * it and PHPStan resolves process() to the default NullProcessor's null. This states the
             * contract the processor does carry.
             *
             * @var string $pdf
             */
            $pdf = $builder->generate()->process();
        } catch (GotenbergException|HttpClientException $e) {
            throw new HttpException(
                Response::HTTP_BAD_GATEWAY,
                'Le service de génération PDF est momentanément indisponible. Veuillez réessayer.',
                $e,
            );
        }

        return $pdf;
    }

    private function renderHtml(
        Setlist $setlist,
        SetlistPdfOptions $options,
        int $totalDurationSeconds,
        int $missingDurationItems,
        SetlistPdfFont $font,
        ?float $measureWidthMm = null,
    ): string {
        $context = [
            'setlist' => $setlist,
            'options' => $options,
            'total_duration_seconds' => $totalDurationSeconds,
            'missing_duration_items' => $missingDurationItems,
            'font' => $font,
        ];

        if ($measureWidthMm !== null) {
            $context['measure_width_mm'] = $measureWidthMm;
        }

        return $this->twig->render($this->template($options->layout), $context);
    }

    private function template(SetlistPdfLayout $layout): string
    {
        return $layout === SetlistPdfLayout::Compact
            ? 'pdf/setlist/setlist_compact.html.twig'
            : 'pdf/setlist/setlist_large.html.twig';
    }
}
