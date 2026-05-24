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
 * choice, font registration and footer/page-counter rendering.
 */
readonly class SetlistPdfRenderer
{
    private const string FONT_DIR = '/assets/fonts/pdf/';
    private const string FONT_CACHE_DIR = '/var/cache/dompdf/';
    private const int FOOTER_BOTTOM_OFFSET_PT = 28;

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
        ]);

        $fontDir = $this->projectDir . self::FONT_DIR;
        $fontCache = $this->projectDir . self::FONT_CACHE_DIR;
        if (!is_dir($fontCache)) {
            @mkdir($fontCache, 0775, recursive: true);
        }

        $dompdfOptions = new Options();
        $dompdfOptions->setDefaultFont($fontFamily);
        $dompdfOptions->setIsRemoteEnabled(false);
        $dompdfOptions->setFontDir($fontDir);
        $dompdfOptions->setFontCache($fontCache);
        // chroot defaults to dompdf's vendor dir; widen it so registerFont()
        // can read our bundled TTFs. Without this, dompdf silently falls back
        // to its built-in Helvetica.
        //
        // NOTE: setChroot REPLACES the default chroot (it does not extend it).
        // If a template ever starts loading local images (e.g. a band logo via
        // <img src="...">), add the relevant asset dir to this array - otherwise
        // dompdf will silently skip those resources too.
        $dompdfOptions->setChroot([$fontDir]);

        $dompdf = new Dompdf($dompdfOptions);
        $this->registerBundledFonts($dompdf, $fontDir);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->stampFooter($dompdf, $fontFamily);

        return $dompdf->output();
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

    /**
     * Canvas-based footer ensures every page shows a real "X / Y" counter -
     * the CSS counter(pages) approach is not reliably rendered by dompdf
     * inside a position:fixed element.
     */
    private function stampFooter(Dompdf $dompdf, string $fontFamily): void
    {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->get_font($fontFamily, 'normal');
        $size = 8;

        $date = (new \DateTimeImmutable())->format('d/m/Y');
        $text = sprintf('MusicAll · %s · {PAGE_NUM}/{PAGE_COUNT}', $date);

        // Width is computed with placeholders replaced by representative
        // values - page numbers up to 99 fit cleanly without re-centering.
        $widthSample = sprintf('MusicAll · %s · 99/99', $date);
        $textWidth = $fontMetrics->getTextWidth($widthSample, $font, $size);
        $pageWidth = $canvas->get_width();
        $x = ($pageWidth - $textWidth) / 2;
        $y = $canvas->get_height() - self::FOOTER_BOTTOM_OFFSET_PT;

        $canvas->page_text($x, $y, $text, $font, $size, [0.55, 0.55, 0.55]);
    }
}
