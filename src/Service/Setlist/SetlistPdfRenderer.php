<?php declare(strict_types=1);

namespace App\Service\Setlist;

use App\Entity\BandSpace\Setlist;
use App\Enum\BandSpace\SetlistPdfLayout;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Wraps dompdf — callers depend only on render(). Encapsulates Twig template
 * choice and dompdf configuration; if the library is ever swapped, only this
 * class changes.
 */
readonly class SetlistPdfRenderer
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function render(Setlist $setlist, SetlistPdfOptions $options, int $totalDurationSeconds): string
    {
        $template = $options->layout === SetlistPdfLayout::Compact
            ? 'pdf/setlist/setlist_compact.html.twig'
            : 'pdf/setlist/setlist_large.html.twig';

        $html = $this->twig->render($template, [
            'setlist' => $setlist,
            'options' => $options,
            'total_duration_seconds' => $totalDurationSeconds,
        ]);

        $dompdfOptions = new Options();
        $dompdfOptions->setDefaultFont('DejaVu Sans');
        $dompdfOptions->setIsRemoteEnabled(false);

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
