<?php declare(strict_types=1);

namespace App\Service\BandSpace\TechRider;

use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\BandSpace\TechRiderPatchRow;
use App\Enum\BandSpace\TechRiderItemType;
use App\Enum\BandSpace\TechRiderPatchDirection;
use App\Enum\BandSpace\TechRiderStagePlotIcon;
use Sensiolabs\GotenbergBundle\Builder\BuilderFileInterface;
use Sensiolabs\GotenbergBundle\Builder\Pdf\HtmlPdfBuilder;
use Sensiolabs\GotenbergBundle\Builder\Pdf\MergePdfBuilder;
use Sensiolabs\GotenbergBundle\Enumeration\PaperSize;
use Sensiolabs\GotenbergBundle\Enumeration\Unit;
use Sensiolabs\GotenbergBundle\Exception\ExceptionInterface as GotenbergException;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\Processor\InMemoryProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Twig\Environment;

/**
 * Renders a whole tech rider to PDF. Callers depend only on render() returning the bytes.
 *
 * A rider is not one document but a sequence, because a document item points at a real PDF that
 * cannot share a page with rider content. So the rider is walked into **segments**: contiguous runs
 * of ordinary items become one HTML render each, and every mergeable attachment becomes a segment of
 * its own. A rider with no PDF attachment is a single segment and costs exactly one request, the same
 * as the setlist export.
 *
 * Bytes rather than a stream, for the reason SetlistPdfRenderer records: GotenbergFileResult::stream()
 * builds its own Content-Disposition with no ASCII fallback, which is the #731 crash.
 */
readonly class TechRiderPdfRenderer
{
    private const string FONT_DIRECTORY = 'assets/fonts/pdf';
    private const string FONT_FAMILY = 'Rider Inter';
    private const string FONT_REGULAR_FILE = 'Inter-Regular.ttf';
    private const string FONT_BOLD_FILE = 'Inter-Bold.ttf';

    /** Where the stage plot icons live, under public/ and unhashed so the path is predictable. */
    private const string ICON_DIRECTORY = 'public';

    private const float MARGIN_TOP_MM = 16.0;
    private const float MARGIN_BOTTOM_MM = 14.0;
    private const float MARGIN_SIDE_MM = 14.0;

    /** Matches BASE_ICON_PERCENT in assets/js/constants/stagePlot.js, so a plot prints to scale. */
    private const float BASE_ICON_PERCENT = 6.0;

    /** Matches DEFAULT_ASPECT_RATIO in the editor, for a plot saved before `stage` existed. */
    private const float DEFAULT_ASPECT_RATIO = 1.4;

    /**
     * An attachment above this is named rather than included.
     *
     * A band space file may be up to 500 MiB, which must never reach this process: even streamed to
     * disk it would be uploaded to Gotenberg and merged. A rider attachment is a stage plan or a
     * photo, so this is generous, and the size is known from the version row without reading a byte.
     */
    private const int MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private Environment $twig,
        private GotenbergPdfInterface $gotenberg,
        private TipTapHtmlRenderer $tipTapRenderer,
        private TechRiderContactsRenderer $contactsRenderer,
        private TechRiderAttachmentReader $attachmentReader,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function render(TechRider $rider): string
    {
        $filesystem = new Filesystem();
        $workspace = sprintf('%s/tech-rider-%s', sys_get_temp_dir(), bin2hex(random_bytes(8)));
        $filesystem->mkdir($workspace);

        try {
            return $this->renderSegments($rider, $workspace);
        } finally {
            // Whatever happened, the spooled attachments go. They can be hundreds of megabytes.
            $filesystem->remove($workspace);
        }
    }

    /**
     * Walks the rider into segments, then renders and if necessary merges them.
     */
    private function renderSegments(TechRider $rider, string $workspace): string
    {
        /** @var list<array{html: string, assets: list<string>}|array{pdf: string}> $segments */
        $segments = [];

        /** @var list<array<string, mixed>> $pending */
        $pending = [];
        /** @var list<string> $pendingAssets */
        $pendingAssets = [];
        $isFirstHtmlSegment = true;

        $flush = function () use ($rider, &$segments, &$pending, &$pendingAssets, &$isFirstHtmlSegment): void {
            // A cover with nothing after it is still a document; an empty trailing run is not.
            if ($pending === [] && !$isFirstHtmlSegment) {
                return;
            }

            $segments[] = [
                'html' => $this->renderHtml($rider, $pending, $isFirstHtmlSegment),
                'assets' => array_values(array_unique($pendingAssets)),
            ];
            $isFirstHtmlSegment = false;
            $pending = [];
            $pendingAssets = [];
        };

        foreach ($this->includedItems($rider) as $item) {
            if ($item->type === TechRiderItemType::Document) {
                $attachment = $this->attachmentReader->prepare($item, $workspace, self::MAX_ATTACHMENT_BYTES);

                if ($attachment['kind'] === 'merge') {
                    $flush();
                    $segments[] = ['pdf' => $attachment['path']];

                    continue;
                }

                if ($attachment['kind'] === 'image') {
                    $pendingAssets[] = $attachment['path'];
                }

                $pending[] = $this->documentViewModel($item, $attachment);

                continue;
            }

            $pending[] = $this->viewModel($item, $pendingAssets);
        }

        $flush();

        return $this->produce($segments, $workspace);
    }

    /**
     * @param list<array{html: string, assets: list<string>}|array{pdf: string}> $segments
     */
    private function produce(array $segments, string $workspace): string
    {
        // The ordinary rider: one render, no temp file, no merge.
        if (count($segments) === 1 && isset($segments[0]['html'])) {
            return $this->generate($this->htmlBuilder($segments[0]['html'], $segments[0]['assets']));
        }

        $filesystem = new Filesystem();
        $paths = [];
        // Gotenberg merges in **alphabetical order of filename**, not in the order the files are
        // sent, so the ordinal is what preserves the composed order of the rider. Named any other
        // way, an attachment called "Plan de salle.pdf" sorts before the cover page.
        //
        // The width is derived rather than fixed, because alphabetical is not numeric: with two
        // digits, "100.pdf" sorts ahead of "20.pdf" and a rider with enough attachments comes out
        // shuffled. Nothing caps how many items a rider may hold.
        $width = max(2, strlen((string) (count($segments) - 1)));

        foreach ($segments as $index => $segment) {
            $path = sprintf('%s/%0' . $width . 'd.pdf', $workspace, $index);

            if (isset($segment['pdf'])) {
                $filesystem->rename($segment['pdf'], $path);
                $paths[] = $path;

                continue;
            }

            // Merging takes paths, never bytes, and insists on a .pdf extension.
            $filesystem->dumpFile($path, $this->generate($this->htmlBuilder($segment['html'], $segment['assets'])));
            $paths[] = $path;
        }

        return $this->generate($this->gotenberg->merge()->files(...$paths)->processor(new InMemoryProcessor()));
    }

    /**
     * Returns the marker interface rather than HtmlPdfBuilder because in dev every builder is wrapped
     * in a TraceableBuilder that proxies through __call, so a concrete return type errors on the first
     * call. The docblock is what keeps the chain statically checked.
     *
     * @param list<string> $assets
     *
     * @return HtmlPdfBuilder
     */
    private function htmlBuilder(string $html, array $assets): BuilderFileInterface
    {
        $fontDirectory = $this->projectDir . '/' . self::FONT_DIRECTORY;

        return $this->gotenberg->html()
            ->contentRaw($html)
            ->assets(
                $fontDirectory . '/' . self::FONT_REGULAR_FILE,
                $fontDirectory . '/' . self::FONT_BOLD_FILE,
                ...$assets,
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
            ->processor(new InMemoryProcessor());
    }

    /**
     * A failed render is a dependency failure, not a client mistake, so it is a 502 rather than the
     * 500 an uncaught transport error would produce.
     */
    private function generate(BuilderFileInterface $builder): string
    {
        try {
            /**
             * InMemoryProcessor is ProcessorInterface<string>, but the builders are not generic over
             * their processor, so PHPStan resolves process() to the default NullProcessor's null.
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

    /**
     * The items that belong in the document, in the order they were composed.
     *
     * Both halves matter. isIncluded is filtered nowhere else on the server, so this is the first and
     * only place it is honoured. And the sort is not optional: the repository's fetch join carries no
     * ORDER BY, and an entity OrderBy does not apply to a fetch-joined collection.
     *
     * @return list<TechRiderItem>
     */
    private function includedItems(TechRider $rider): array
    {
        $items = array_values(array_filter(
            $rider->items->toArray(),
            static fn (TechRiderItem $item): bool => $item->isIncluded,
        ));

        usort($items, static fn (TechRiderItem $a, TechRiderItem $b): int => $a->position <=> $b->position);

        return $items;
    }

    /**
     * @param list<string> $assets collects the stage plot icons this item needs
     *
     * @return array<string, mixed>
     */
    private function viewModel(TechRiderItem $item, array &$assets): array
    {
        $base = ['type' => $item->type->value, 'title' => $item->title];

        return match ($item->type) {
            TechRiderItemType::Text => $base + ['html' => $this->tipTapRenderer->render($item->content)],
            TechRiderItemType::PatchList => $base + $this->patchListViewModel($item),
            TechRiderItemType::StagePlot => $base + $this->stagePlotViewModel($item, $assets),
            TechRiderItemType::Contacts => $base + $this->contactsViewModel($item),
            TechRiderItemType::Document => $base,
        };
    }

    /**
     * @param array{kind: string, path?: string, name: string, reason?: string} $attachment
     *
     * @return array<string, mixed>
     */
    private function documentViewModel(TechRiderItem $item, array $attachment): array
    {
        $base = ['type' => $item->type->value, 'title' => $item->title, 'name' => $attachment['name']];

        if ($attachment['kind'] === 'image') {
            return $base + ['image' => basename($attachment['path'] ?? '')];
        }

        return $base + ['reason' => $attachment['reason'] ?? ''];
    }

    /**
     * Patch rows sort by direction **then** position: position restarts per direction, so on its own
     * it is not a total order and the two tables would interleave.
     *
     * @return array<string, mixed>
     */
    private function patchListViewModel(TechRiderItem $item): array
    {
        $rows = $item->patchRows->toArray();
        usort($rows, static fn (TechRiderPatchRow $a, TechRiderPatchRow $b): int => $a->position <=> $b->position);

        $partition = static fn (TechRiderPatchDirection $direction): array => array_values(array_filter(
            $rows,
            static fn (TechRiderPatchRow $row): bool => $row->direction === $direction,
        ));

        return [
            'inputs' => $partition(TechRiderPatchDirection::Input),
            'outputs' => $partition(TechRiderPatchDirection::Output),
        ];
    }

    /**
     * @param list<string> $assets
     *
     * @return array<string, mixed>
     */
    private function stagePlotViewModel(TechRiderItem $item, array &$assets): array
    {
        $document = $item->content ?? [];
        $aspectRatio = $document['stage']['aspect_ratio'] ?? null;

        $elements = [];
        foreach (is_array($document['elements'] ?? null) ? $document['elements'] : [] as $element) {
            if (!is_array($element)) {
                continue;
            }

            $icon = TechRiderStagePlotIcon::tryFrom(is_string($element['icon'] ?? null) ? $element['icon'] : '');
            if ($icon === null) {
                continue;
            }

            $assets[] = $this->iconPath($icon);
            $scale = is_numeric($element['scale'] ?? null) ? (float) $element['scale'] : 1.0;

            $elements[] = [
                'image' => $icon->value . '.png',
                'left' => round(((float) ($element['x'] ?? 0)) * 100, 4),
                'top' => round(((float) ($element['y'] ?? 0)) * 100, 4),
                'width' => round(self::BASE_ICON_PERCENT * $scale, 4),
                'rotation' => is_int($element['rotation'] ?? null) ? $element['rotation'] : 0,
                'label' => is_string($element['label'] ?? null) ? $element['label'] : null,
                'colour' => $this->colourHex($element['colour'] ?? null),
            ];
        }

        $legend = [];
        foreach (is_array($document['legend'] ?? null) ? $document['legend'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $icon = TechRiderStagePlotIcon::tryFrom(is_string($entry['icon'] ?? null) ? $entry['icon'] : '');
            if ($icon === null) {
                continue;
            }

            $assets[] = $this->iconPath($icon);
            $legend[] = [
                'image' => $icon->value . '.png',
                'label' => is_string($entry['label'] ?? null) && $entry['label'] !== ''
                    ? $entry['label']
                    : $icon->label(),
            ];
        }

        return [
            'aspect_ratio' => is_numeric($aspectRatio) ? (float) $aspectRatio : self::DEFAULT_ASPECT_RATIO,
            'elements' => $elements,
            'legend' => $legend,
        ];
    }

    /**
     * The lines come from TechRiderContactsRenderer rather than being formatted here, so the document
     * a venue receives says exactly what the band proof read on screen.
     *
     * @return array<string, mixed>
     */
    private function contactsViewModel(TechRiderItem $item): array
    {
        $showEmails = ($item->content['showEmails'] ?? false) === true;
        $rendered = $this->contactsRenderer->render($item->techRider->bandSpace, $showEmails);

        return [
            'lines' => $rendered['lines'],
            'emails' => $rendered['emails'],
            // The note is not part of the API's contacts block, so it is read straight from content.
            'note_html' => $this->tipTapRenderer->render(
                is_array($item->content['note'] ?? null) ? $item->content['note'] : null,
            ),
        ];
    }

    private function colourHex(mixed $colour): ?string
    {
        if (!is_string($colour)) {
            return null;
        }

        return \App\Enum\BandSpace\TechRiderColour::tryFrom($colour)?->hex();
    }

    private function iconPath(TechRiderStagePlotIcon $icon): string
    {
        return $this->projectDir . '/' . self::ICON_DIRECTORY . $icon->imagePath();
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function renderHtml(TechRider $rider, array $items, bool $withCover): string
    {
        return $this->twig->render('pdf/tech_rider/rider.html.twig', [
            'rider' => $rider,
            'items' => $items,
            'with_cover' => $withCover,
            'generated_at' => (new \DateTimeImmutable())->format('d/m/Y'),
            'font_family' => self::FONT_FAMILY,
            'font_regular_file' => self::FONT_REGULAR_FILE,
            'font_bold_file' => self::FONT_BOLD_FILE,
        ]);
    }
}
