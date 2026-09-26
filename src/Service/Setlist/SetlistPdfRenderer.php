<?php declare(strict_types=1);

namespace App\Service\Setlist;

use App\Entity\BandSpace\Setlist;
use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;
use App\Service\BandSpace\Song\SongSheetBuilder;
use App\Service\BandSpace\Song\SongSheetOptions;
use App\Service\Pdf\HtmlPdfGenerator;
use Sensiolabs\GotenbergBundle\Enumeration\Unit;
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
        private HtmlPdfGenerator $pdf,
        private SongSheetBuilder $sheetBuilder,
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

        $builder = $this->pdf->builder(
            $this->renderHtml($setlist, $options, $totalDurationSeconds, $missingDurationItems, $font),
            $font,
        );

        if ($scale !== null) {
            // Chromium's print scale widens the layout viewport by 1/scale, so the table still
            // spans the full printable width while every length shrinks together. That is why the
            // templates carry no scale arithmetic of their own any more.
            $builder->scale($scale);
        }

        return $this->pdf->generate($builder);
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
        // With the lyrics the document is several pages by design; one page only ever meant the list.
        if (!$options->fitToOnePage || $options->showLyrics || $setlist->items->count() > self::MAX_FIT_ITEMS) {
            return null;
        }

        $naturalHeightPt = $this->measureContentHeightPt($setlist, $options, $totalDurationSeconds, $missingDurationItems, $font);
        if ($naturalHeightPt <= 0.0) {
            return null;
        }

        $availableHeightPt = (HtmlPdfGenerator::PAGE_HEIGHT_MM - HtmlPdfGenerator::MARGIN_TOP_MM - HtmlPdfGenerator::MARGIN_BOTTOM_MM) * self::POINTS_PER_MM;
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
            measureWidthMm: HtmlPdfGenerator::PAGE_WIDTH_MM - (2 * HtmlPdfGenerator::MARGIN_SIDE_MM),
        );

        $measurement = $this->pdf->generate(
            $this->pdf->builder($html, $font)
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
            'lyrics_sheets' => $options->showLyrics ? $this->lyricsSheets($setlist, $options) : [],
        ];

        if ($measureWidthMm !== null) {
            $context['measure_width_mm'] = $measureWidthMm;
        }

        return $this->twig->render($this->template($options->layout), $context);
    }

    /**
     * Every song of the set that has lyrics, in running order, once even when it is played twice.
     *
     * @return list<array<string, mixed>>
     */
    private function lyricsSheets(Setlist $setlist, SetlistPdfOptions $options): array
    {
        $sheetOptions = new SongSheetOptions(showChords: $options->lyricsChords, showSingers: $options->lyricsSingers);
        $sheets = [];
        foreach ($setlist->items as $item) {
            $song = $item->song;
            if ($song === null || $song->lyrics === null || isset($sheets[(string) $song->id])) {
                continue;
            }
            $sheets[(string) $song->id] = $this->sheetBuilder->build($song, $sheetOptions);
        }

        return array_values($sheets);
    }

    private function template(SetlistPdfLayout $layout): string
    {
        return $layout === SetlistPdfLayout::Compact
            ? 'pdf/setlist/setlist_compact.html.twig'
            : 'pdf/setlist/setlist_large.html.twig';
    }
}
