<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\AgendaItem;
use App\Entity\BandSpace\AgendaFeedToken;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\AgendaFeedTokenRepository;
use App\Service\BandSpace\AgendaAggregator;
use App\Service\BandSpace\IcalFeedBuilder;
use App\Service\BandSpace\ShareTokenService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Serves one member's agenda as an RFC 5545 document to whatever calendar client holds their token.
 *
 * @implements ProviderInterface<Response>
 */
readonly class AgendaFeedDownloadProvider implements ProviderInterface
{
    /**
     * Manual entries only. Tasks, finance rows and absences are context for the agenda screen, which
     * has filter chips and per source colours; a subscribed calendar has neither, and dropping four
     * sources into one uncoloured calendar is how a subscription gets deleted. A second source, if
     * ever asked for, becomes its own URL so the client can colour and hide it on its own.
     */
    private const string PUBLISHED_SOURCE = 'manual';

    private const string WINDOW_START = '-12 months';

    private const string WINDOW_END = '+24 months';

    public function __construct(
        private AgendaFeedTokenRepository $feedTokenRepository,
        private ShareTokenService $tokenService,
        private AgendaAggregator $agendaAggregator,
        private IcalFeedBuilder $icalFeedBuilder,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        #[Target('band_space_agenda_feed_access')]
        private RateLimiterFactoryInterface $feedAccessLimiter,
        #[Target('band_space_agenda_feed_miss')]
        private RateLimiterFactoryInterface $feedMissLimiter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $feedToken = $this->feedTokenRepository->findOneByTokenHash(
            $this->tokenService->hashOf((string) $uriVariables['token']),
        );

        // The lookup comes before either limiter on purpose. Keying the access limiter on the token
        // is what makes it safe for a feed, since a calendar client's requests arrive from its
        // vendor's shared fetcher pool and a per IP bucket would let one band's polling throttle
        // another's. But a bucket per token asked for would let anyone mint unbounded keys in
        // Valkey, so an unknown token is charged to the caller's own address instead.
        if (!$feedToken instanceof AgendaFeedToken) {
            $this->consume($this->feedMissLimiter, $request?->getClientIp() ?? 'unknown');

            throw new NotFoundHttpException('Flux introuvable');
        }

        $this->consume($this->feedAccessLimiter, $feedToken->tokenHash);

        $membership = $feedToken->membership;
        // The token outlives the membership: a row goes to Left or Kicked and keeps its
        // leftDatetime, it is never deleted, so the FK's onDelete CASCADE never fires. Without this
        // line somebody thrown out of the band would keep reading its dates indefinitely. The leave
        // and kick processors drop the token too; this is the check that does not depend on them.
        if ($membership->status !== MembershipStatus::Active) {
            throw new NotFoundHttpException('Flux introuvable');
        }

        // Snapped to midnight rather than computed to the second, so two polls on the same day
        // expand the same occurrences and produce the same bytes. Without that the ETag below would
        // change on every request and never save anything.
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $bandSpace = $membership->bandSpace;

        $items = array_filter(
            $this->agendaAggregator->aggregate(
                $bandSpace,
                $membership,
                $today->modify(self::WINDOW_START),
                $today->modify(self::WINDOW_END),
            ),
            static fn(AgendaItem $item): bool => $item->source === self::PUBLISHED_SOURCE,
        );

        $body = $this->icalFeedBuilder->build($items, $bandSpace->name, $today);

        $feedToken->accessCount += 1;
        $feedToken->lastAccessDatetime = new DateTimeImmutable();
        $this->entityManager->flush();

        return $this->respond($body, $request);
    }

    /**
     * The document, or a 304 when the client already holds it.
     *
     * The ETag saves the transfer, not the aggregation: it is computed from the body, so the work
     * has already happened by the time we know the client is up to date. That is the right trade for
     * now, since the expansion is a handful of queries while a feed of a busy year is tens of
     * kilobytes fetched by every member's client. The body only varies by band space and by day, so
     * that pair is where a Valkey cache would key if one is ever measured to be worth it.
     */
    private function respond(string $body, ?Request $request): Response
    {
        $response = new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            // The body is member authored, so it is served the way BandSpaceFileDownloadProvider
            // serves an upload. No browser promotes an explicit text/calendar to text/html today,
            // which makes this defence in depth rather than a fix.
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag(hash('xxh128', $body));
        $response->setPrivate();
        $response->setMaxAge(3600);

        if ($request instanceof Request) {
            // Turns the response into a bodyless 304 when If-None-Match matches.
            $response->isNotModified($request);
        }

        return $response;
    }

    /**
     * A 429 carrying Retry-After.
     *
     * The bare RateLimitExceededException maps to a 429 with no header, which is worse than usual
     * here: a calendar client shows the user nothing and quietly stops refreshing, so a stale
     * calendar is the only symptom. Telling it when to come back is the least we can do.
     */
    private function consume(RateLimiterFactoryInterface $limiterFactory, string $key): void
    {
        $limit = $limiterFactory->create($key)->consume();
        if ($limit->isAccepted()) {
            return;
        }

        throw new TooManyRequestsHttpException(
            max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            'Trop de requêtes sur ce flux',
        );
    }
}
