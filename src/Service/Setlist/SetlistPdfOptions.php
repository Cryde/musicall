<?php declare(strict_types=1);

namespace App\Service\Setlist;

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
    ) {
    }
}
