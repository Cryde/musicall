<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

enum SetlistPdfFont: string
{
    case Inter = 'inter';
    case AtkinsonHyperlegible = 'atkinson_hyperlegible';
    case SourceSerif = 'source_serif';

    /**
     * dompdf font family name as registered in the font cache.
     */
    public function dompdfFamily(): string
    {
        return match ($this) {
            self::Inter => 'Inter',
            self::AtkinsonHyperlegible => 'Atkinson Hyperlegible',
            self::SourceSerif => 'Source Serif',
        };
    }

    /**
     * File name of the regular-weight TTF, relative to assets/fonts/pdf/.
     */
    public function regularFile(): string
    {
        return match ($this) {
            self::Inter => 'Inter-Regular.ttf',
            self::AtkinsonHyperlegible => 'AtkinsonHyperlegible-Regular.ttf',
            self::SourceSerif => 'SourceSerif-Regular.ttf',
        };
    }

    /**
     * File name of the bold-weight TTF, relative to assets/fonts/pdf/.
     */
    public function boldFile(): string
    {
        return match ($this) {
            self::Inter => 'Inter-Bold.ttf',
            self::AtkinsonHyperlegible => 'AtkinsonHyperlegible-Bold.ttf',
            self::SourceSerif => 'SourceSerif-Bold.ttf',
        };
    }

    public static function defaultFor(SetlistPdfLayout $layout): self
    {
        return match ($layout) {
            SetlistPdfLayout::Compact => self::AtkinsonHyperlegible,
            SetlistPdfLayout::Large => self::Inter,
        };
    }
}
