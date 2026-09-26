<?php declare(strict_types=1);

namespace App\Service\Pdf;

use App\Enum\BandSpace\SetlistPdfFont;
use Sensiolabs\GotenbergBundle\Builder\BuilderFileInterface;
use Sensiolabs\GotenbergBundle\Builder\BuilderInterface;
use Sensiolabs\GotenbergBundle\Builder\Pdf\HtmlPdfBuilder;
use Sensiolabs\GotenbergBundle\Enumeration\PaperSize;
use Sensiolabs\GotenbergBundle\Enumeration\Unit;
use Sensiolabs\GotenbergBundle\Exception\ExceptionInterface as GotenbergException;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Sensiolabs\GotenbergBundle\Processor\InMemoryProcessor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * An A4 HTML document through Gotenberg, shared by the setlist and the song exports (#1055) so both
 * print on the same page box and fail the same way.
 */
readonly class HtmlPdfGenerator
{
    /** Relative to the project root. The two TTFs of the chosen family are uploaded from here. */
    public const string FONT_DIRECTORY = 'assets/fonts/pdf';

    /**
     * The page box lives here and nowhere else. It used to be a CSS @page rule, which cannot stay:
     * a CSS margin silently overrides the builder's margin fields, so the setlist fit arithmetic and
     * the real page would have disagreed with no way to tell which had won.
     */
    public const float PAGE_HEIGHT_MM = 297.0;
    public const float PAGE_WIDTH_MM = 210.0;
    public const float MARGIN_TOP_MM = 18.0;
    public const float MARGIN_BOTTOM_MM = 14.0;
    public const float MARGIN_SIDE_MM = 14.0;

    public function __construct(
        private GotenbergPdfInterface $gotenberg,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * Returns the marker interface rather than HtmlPdfBuilder on purpose: in dev the bundle wraps
     * every builder in a TraceableBuilder for its profiler, which proxies the option methods through
     * __call, so a concrete return type here type errors on the first call. The docblock is what
     * keeps the fluent chain statically checked.
     *
     * @return HtmlPdfBuilder
     */
    public function builder(string $html, SetlistPdfFont $font): BuilderInterface
    {
        $fontDirectory = $this->projectDir . '/' . self::FONT_DIRECTORY;

        return $this->gotenberg->html()
            ->contentRaw($html)
            // Only the chosen family, two files. dompdf had to register all three on every render.
            ->assets(
                $fontDirectory . '/' . $font->regularFile(),
                $fontDirectory . '/' . $font->boldFile(),
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
            // InMemoryProcessor warns against production use because it holds the whole document in
            // a string. That is the right trade here and changes nothing: the caller already puts
            // the full body into a Response, as dompdf's output() did, and a setlist PDF measures in
            // hundreds of kilobytes. Streaming instead would mean giving up the bytes seam, and with
            // it the Content-Disposition handling that #731 exists for.
            ->processor(new InMemoryProcessor());
    }

    /**
     * A failed render is a dependency failure, not a client mistake, so it becomes a 502 rather than
     * the 500 an uncaught transport error would produce. Only Gotenberg's own failures and transport
     * errors are caught; a Twig or logic error still surfaces as itself.
     */
    public function generate(BuilderFileInterface $builder): string
    {
        try {
            /**
             * InMemoryProcessor is declared ProcessorInterface<string>, but HtmlPdfBuilder extends
             * AbstractBuilder without an @extends annotation, so the processor generic never reaches
             * it and PHPStan resolves process() to the default NullProcessor's null. This states the
             * contract the processor does carry.
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
}
