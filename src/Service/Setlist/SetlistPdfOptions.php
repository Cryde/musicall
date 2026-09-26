<?php declare(strict_types=1);

namespace App\Service\Setlist;

use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;

final readonly class SetlistPdfOptions
{
    public function __construct(
        public SetlistPdfLayout $layout = SetlistPdfLayout::Large,
        public bool $showTempo = true,
        public bool $showKey = true,
        public bool $showDurations = true,
        public bool $showNotes = false,
        public bool $showTransitions = false,
        public ?SetlistPdfFont $font = null,
        public bool $fitToOnePage = false,
        /** Each song's lyrics after the list, one per page (#1055). */
        public bool $showLyrics = false,
        public bool $lyricsChords = true,
        public bool $lyricsSingers = true,
    ) {
    }

    /**
     * Returns the explicit font choice, or the layout's default if none set.
     */
    public function effectiveFont(): SetlistPdfFont
    {
        return $this->font ?? SetlistPdfFont::defaultFor($this->layout);
    }
}
