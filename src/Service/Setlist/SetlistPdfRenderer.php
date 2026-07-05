<?php declare(strict_types=1);

namespace App\Service\Setlist;

use App\Entity\BandSpace\Setlist;
use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Wraps dompdf - callers depend only on render(). Encapsulates Twig template
 * choice, font registration and the optional "fit to one page" shrink loop.
 */
readonly class SetlistPdfRenderer
{
    private const string FONT_DIR = '/assets/fonts/pdf/';
    private const string FONT_CACHE_DIR = '/var/cache/dompdf/';

    /**
     * Above this many items a one-page render would be illegible, so the
     * fit-to-one-page request is ignored and we fall back to a normal render.
     * Mirrors the frontend cap (and blocks a crafted URL forcing an unreadable
     * fit on a huge set).
     */
    private const int MAX_FIT_ITEMS = 15;

    /**
     * Descending scale factors tried when fitting to one page; the first that
     * yields a single page wins. The last value is the floor.
     *
     * @var list<float>
     */
    private const array FIT_SCALES = [1.0, 0.85, 0.72, 0.6, 0.5, 0.42];

    public function __construct(
        private Environment $twig,
        private string $projectDir,
    ) {
    }

    public function render(
        Setlist $setlist,
        SetlistPdfOptions $options,
        int $totalDurationSeconds,
        int $missingDurationItems = 0,
    ): string {
        if (!$options->fitToOnePage || $setlist->items->count() > self::MAX_FIT_ITEMS) {
            return $this->renderAtScale($setlist, $options, $totalDurationSeconds, $missingDurationItems, 1.0)[0];
        }

        $lastPdf = '';
        foreach (self::FIT_SCALES as $scale) {
            [$lastPdf, $pageCount] = $this->renderAtScale($setlist, $options, $totalDurationSeconds, $missingDurationItems, $scale);
            if ($pageCount <= 1) {
                return $lastPdf;
            }
        }

        // Could not fit even at the floor scale (should not happen within the
        // item cap) - return the smallest attempt as the best effort.
        return $lastPdf;
    }

    /**
     * Renders the setlist at the given scale factor and returns the PDF binary
     * together with its page count, so the caller can shrink until it fits.
     *
     * @return array{0: string, 1: int}
     */
    private function renderAtScale(
        Setlist $setlist,
        SetlistPdfOptions $options,
        int $totalDurationSeconds,
        int $missingDurationItems,
        float $scale,
    ): array {
        $template = $options->layout === SetlistPdfLayout::Compact
            ? 'pdf/setlist/setlist_compact.html.twig'
            : 'pdf/setlist/setlist_large.html.twig';

        $font = $options->effectiveFont();
        $fontFamily = $font->dompdfFamily();

        $html = $this->twig->render($template, [
            'setlist' => $setlist,
            'options' => $options,
            'total_duration_seconds' => $totalDurationSeconds,
            'missing_duration_items' => $missingDurationItems,
            'font_family' => $fontFamily,
            'scale' => $scale,
        ]);

        $assetFontDir = $this->projectDir . self::FONT_DIR;
        $fontCache = $this->projectDir . self::FONT_CACHE_DIR;
        if (!is_dir($fontCache)) {
            @mkdir($fontCache, 0775, recursive: true);
        }

        $dompdfOptions = new Options();
        $dompdfOptions->setDefaultFont($fontFamily);
        $dompdfOptions->setIsRemoteEnabled(false);
        // dompdf writes the compiled .ufm/.ttf into fontDir (NOT fontCache), and
        // names them from a hash of the absolute source path - which changes with
        // every release directory - so it regenerates them on every deploy. fontDir
        // must therefore be writable: point it at var/cache/dompdf. The read-only
        // bundled TTFs shipped in assets/ are still read via the file:// paths in
        // registerFont(). Pointing fontDir at the read-only assets dir returned a
        // 500 in production ("Permission denied" writing inter_normal_*.ufm).
        $dompdfOptions->setFontDir($fontCache);
        $dompdfOptions->setFontCache($fontCache);
        // chroot REPLACES the default (it does not extend it). registerFont()
        // validates the source TTF path against it, so it must include the asset
        // dir we read the bundled fonts from; the writable cache dir is included too.
        //
        // NOTE: if a template ever starts loading local images (e.g. a band logo
        // via <img src="...">), add the relevant asset dir here - otherwise dompdf
        // will silently skip those resources.
        $dompdfOptions->setChroot([$assetFontDir, $fontCache]);

        $dompdf = new Dompdf($dompdfOptions);
        $this->registerBundledFonts($dompdf, $assetFontDir);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pageCount = $dompdf->getCanvas()->get_page_count();

        return [(string) $dompdf->output(), $pageCount];
    }

    /**
     * dompdf's registerFont() rejects paths without a recognised URL scheme
     * (the implicit '' protocol is not in the allowedProtocols allowlist), so
     * we must prefix each absolute path with file://.
     */
    private function registerBundledFonts(Dompdf $dompdf, string $fontDir): void
    {
        $fontMetrics = $dompdf->getFontMetrics();
        foreach (SetlistPdfFont::cases() as $font) {
            $family = $font->dompdfFamily();
            $fontMetrics->registerFont(
                ['family' => $family, 'style' => 'normal', 'weight' => 'normal'],
                'file://' . $fontDir . $font->regularFile(),
            );
            $fontMetrics->registerFont(
                ['family' => $family, 'style' => 'normal', 'weight' => 'bold'],
                'file://' . $fontDir . $font->boldFile(),
            );
        }
    }
}
