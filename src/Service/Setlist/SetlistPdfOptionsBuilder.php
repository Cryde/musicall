<?php declare(strict_types=1);

namespace App\Service\Setlist;

use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;
use Symfony\Component\HttpFoundation\Request;

/**
 * Translates an export request's query string into a typed SetlistPdfOptions.
 *
 * Compact is a stage sheet - the per-field toggles are inert and forced off
 * here, defensive against bookmarked / tampered URLs that bypass the UI greying.
 * An invalid ?font= value falls back to the layout's default (no 422).
 */
final readonly class SetlistPdfOptionsBuilder
{
    public function fromRequest(?Request $request): SetlistPdfOptions
    {
        $query = $request?->query;

        $layout = SetlistPdfLayout::tryFrom((string) ($query?->get('layout', 'large') ?? 'large')) ?? SetlistPdfLayout::Large;
        $font = SetlistPdfFont::tryFrom((string) ($query?->get('font') ?? ''));

        if ($layout === SetlistPdfLayout::Compact) {
            return new SetlistPdfOptions(
                layout: $layout,
                showTempo: false,
                showKey: false,
                showDurations: false,
                showNotes: false,
                showTransitions: false,
                font: $font,
            );
        }

        return new SetlistPdfOptions(
            layout: $layout,
            showTempo: $query?->getBoolean('showTempo', true) ?? true,
            showKey: $query?->getBoolean('showKey', true) ?? true,
            showDurations: $query?->getBoolean('showDurations', true) ?? true,
            showNotes: $query?->getBoolean('showNotes', false) ?? false,
            showTransitions: $query?->getBoolean('showTransitions', false) ?? false,
            font: $font,
        );
    }
}
