<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song;

use App\Entity\BandSpace\Song;
use App\Enum\BandSpace\SetlistPdfFont;
use App\Service\Pdf\HtmlPdfGenerator;
use Twig\Environment;

/**
 * One song's lyrics to PDF (#1055). Bytes rather than a stream, for the reason SetlistPdfRenderer
 * records: App\Http\ContentDisposition keeps owning that header.
 */
readonly class SongPdfRenderer
{
    private const SetlistPdfFont FONT = SetlistPdfFont::Inter;

    public function __construct(
        private Environment $twig,
        private HtmlPdfGenerator $pdf,
        private SongSheetBuilder $sheetBuilder,
    ) {
    }

    public function render(Song $song, SongSheetOptions $options): string
    {
        $html = $this->twig->render('pdf/song/song.html.twig', [
            'sheet' => $this->sheetBuilder->build($song, $options),
            'font' => self::FONT,
        ]);

        return $this->pdf->generate($this->pdf->builder($html, self::FONT));
    }
}
