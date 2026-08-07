<?php declare(strict_types=1);

namespace App\Tests\Double;

use Sensiolabs\GotenbergBundle\Builder\Payload;
use Sensiolabs\GotenbergBundle\Client\GotenbergClientInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Stands in for the real Gotenberg client so the suite needs no container, and records what was sent
 * so a test can assert on the request rather than on opaque PDF bytes.
 *
 * The bundle ships its own GotenbergClientAsserter, which is not usable here for two reasons. It
 * keeps only the last call, and the fit-to-one-page path deliberately makes two (measure, then
 * render), as does any test that renders twice. And it answers with an empty body, which would break
 * every test that only ever cared that a PDF came back down the endpoint.
 *
 * Registered in place of sensiolabs_gotenberg.client under when@test in config/services.yaml. The
 * builders resolve that id lazily through a service subscriber, so the substitution reaches them
 * even inside a functional request.
 */
final class RecordingGotenbergClient implements GotenbergClientInterface
{
    /**
     * Taller than an A4 text area, so a fit request has something to shrink and the arithmetic runs
     * for real. Roughly what fifteen compact rows actually measure.
     */
    public const float DEFAULT_CONTENT_HEIGHT_PT = 1223.04;

    private readonly MockHttpClient $httpClient;

    /** @var list<array{endpoint: string, fields: array<string, string>, documents: array<string, string>, assets: array<string, string>}> */
    private array $calls = [];

    private float $contentHeightPt = self::DEFAULT_CONTENT_HEIGHT_PT;

    private ?\Throwable $failure = null;

    private bool $isPassthrough = false;

    public function __construct(private readonly GotenbergClientInterface $inner)
    {
        // A closure factory rather than a list of responses: the factory is asked for a fresh
        // response per request, so a test can render as many times as it likes. A list runs out and
        // fails the second render with "No more response left".
        $this->httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse($this->fakePdf(), [
            'response_headers' => ['content-type' => 'application/pdf'],
        ]));
    }

    public function call(string $endpoint, Payload $payload): ResponseInterface
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $fields = [];
        $documents = [];
        $assets = [];

        foreach ($payload->getFormData()->getParts() as $part) {
            if (!$part instanceof TextPart) {
                continue;
            }

            $body = self::rawBody($part);

            // An uploaded file carries a filename; everything else is a plain form field. Of the
            // files, the ones Gotenberg reads as documents (index.html and friends) arrive as a
            // string, while an asset added with assets() arrives as a File pointing at the source.
            $filename = $part instanceof DataPart ? $part->getFilename() : null;
            if ($filename === null || $filename === '') {
                $fields[(string) $part->getName()] = $part->getBody();

                continue;
            }

            if ($body instanceof File) {
                $assets[$filename] = $body->getPath();

                continue;
            }

            $documents[$filename] = $part->getBody();
        }

        $this->calls[] = [
            'endpoint' => $endpoint,
            'fields' => $fields,
            'documents' => $documents,
            'assets' => $assets,
        ];

        return $this->isPassthrough
            ? $this->inner->call($endpoint, $payload)
            : $this->httpClient->request('POST', $endpoint);
    }

    public function stream(ResponseInterface $response): ResponseStreamInterface
    {
        return $this->isPassthrough ? $this->inner->stream($response) : $this->httpClient->stream($response);
    }

    /**
     * Renders against the real Gotenberg from here on, still recording what was sent. This is how an
     * integration test exercises the live service without rebuilding the wiring by hand.
     */
    public function passthrough(): void
    {
        $this->isPassthrough = true;
    }

    /** Drives the fit arithmetic: the height the fake claims the content occupies. */
    public function withContentHeightPt(float $contentHeightPt): void
    {
        $this->contentHeightPt = $contentHeightPt;
    }

    /** Makes the next call fail, for the unreachable-Gotenberg path. */
    public function failWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @return list<array{endpoint: string, fields: array<string, string>, documents: array<string, string>, assets: array<string, string>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return array{endpoint: string, fields: array<string, string>, documents: array<string, string>, assets: array<string, string>}
     */
    public function lastCall(): array
    {
        $last = end($this->calls);
        if ($last === false) {
            throw new \LogicException('Gotenberg was never called.');
        }

        return $last;
    }

    /** The HTML of the document sent on the given call, defaulting to the last one. */
    public function sentHtml(int $callIndex = -1): string
    {
        $call = $callIndex < 0 ? $this->lastCall() : ($this->calls[$callIndex] ?? throw new \LogicException(
            \sprintf('There was no call number %d.', $callIndex),
        ));

        return $call['documents']['index.html'] ?? throw new \LogicException('No index.html was sent.');
    }

    /**
     * Enough of a PDF for the code under test: the %PDF- signature every caller checks, and a media
     * box the renderer measures the content height from.
     */
    private function fakePdf(): string
    {
        return \sprintf(
            "%%PDF-1.4\n1 0 obj\n<</Type /Page /MediaBox [0 0 595.92 %.2F]>>\nendobj\ntrailer\n<<>>\n%%%%EOF\n",
            $this->contentHeightPt,
        );
    }

    /**
     * The undecorated body, which the public getBody() would stringify. A DataPart built from a File
     * has to stay a File so an uploaded asset can be told apart from an uploaded document.
     */
    private static function rawBody(TextPart $part): mixed
    {
        return \Closure::bind(static fn (TextPart $part): mixed => $part->body, null, TextPart::class)($part);
    }
}
