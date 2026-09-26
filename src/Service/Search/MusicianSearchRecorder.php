<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\ApiResource\Search\AnnounceMusicianFilter;
use App\Entity\Attribute\Instrument;
use App\Entity\Search\MusicianSearchLog;
use App\Enum\Search\AiSearchOutcome;
use App\Enum\Search\MusicianSearchKind;
use App\Model\Search\MusicianSearch;
use App\Repository\Search\MusicianSearchLogRepository;
use App\Service\Bot\BotDetector;
use App\Service\Identifier\DailyVisitorIdentifier;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Keeps a musician search for the frequent searches and the admin (#1075). Best effort: a search
 * that could not be recorded still answers, so a failure here is logged and nothing more.
 */
readonly class MusicianSearchRecorder
{
    /**
     * Crawlers render the search page like a visitor would. BotDetector only knows the link preview
     * bots it serves a static page to, so anything calling itself a robot is left out here as well.
     */
    private const array AUTOMATED_AGENT_MARKERS = ['bot', 'crawl', 'spider', 'slurp', 'headless'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MusicianSearchLogRepository $musicianSearchLogRepository,
        #[Target('search_log')]
        private RateLimiterFactoryInterface $searchLogLimiter,
        private RequestStack $requestStack,
        private DailyVisitorIdentifier $visitorIdentifier,
        private BotDetector $botDetector,
        private Security $security,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Only the first page of a search that asks for something: « Voir plus » is the same search, and
     * the search page's opening list, with no criterion, is nobody's search. Nor is the list a landing
     * page such as « Rechercher un batteur » shows on arrival, which the page flags as `landing`.
     */
    public function recordFiltersSearch(MusicianSearch $search, ?string $locationName, int $firstPageResultCount, bool $landing = false): void
    {
        if ($landing) {
            return;
        }
        $hasLocation = $search->latitude !== null && $search->longitude !== null;
        $asksForSomething = $search->type !== null || $search->instrument instanceof Instrument || $search->styles !== [] || $hasLocation;
        if ($search->page !== 1 || !$asksForSomething) {
            return;
        }

        $this->record(MusicianSearchKind::Filters, function (MusicianSearchLog $log) use ($search, $hasLocation, $locationName, $firstPageResultCount): void {
            $log->type = $search->type;
            $log->instrument = $search->instrument;
            $log->styleIds = array_values(array_map(static fn ($style): string => (string) $style->id, $search->styles));
            if ($hasLocation) {
                $log->latitude = $search->latitude;
                $log->longitude = $search->longitude;
                $log->locationName = $locationName !== null && trim($locationName) !== '' ? trim($locationName) : null;
            }
            $log->firstPageResultCount = $firstPageResultCount;
        });
    }

    public function recordAiSearch(string $query, AiSearchOutcome $outcome, ?AnnounceMusicianFilter $filters = null): void
    {
        $this->record(MusicianSearchKind::Ai, function (MusicianSearchLog $log) use ($query, $outcome, $filters): void {
            $log->aiQuery = mb_substr(trim($query), 0, 255);
            $log->aiOutcome = $outcome;
            if (!$filters instanceof AnnounceMusicianFilter) {
                return;
            }
            $log->type = $filters->type;
            $log->instrument = $filters->instrument !== null
                ? $this->entityManager->getReference(Instrument::class, $filters->instrument)
                : null;
            $log->styleIds = array_values($filters->styles);
            $log->latitude = $filters->latitude;
            $log->longitude = $filters->longitude;
        });
    }

    /** @param callable(MusicianSearchLog): void $fill */
    private function record(MusicianSearchKind $kind, callable $fill): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || $this->isAutomated($request)) {
            return;
        }
        // Past its budget a visitor still gets their search, it is only not kept: the table stays
        // bounded and a script cannot fill the frequent searches on its own.
        if (!$this->searchLogLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return;
        }

        try {
            $now = new DateTimeImmutable();
            $log = new MusicianSearchLog($kind, $this->visitorIdentifier->fromRequest($request, $now), $now);
            $log->authenticated = $this->security->getUser() !== null;
            $fill($log);

            $this->musicianSearchLogRepository->insert($log);
        } catch (\Throwable $exception) {
            $this->logger->warning('Could not record a musician search', ['kind' => $kind->value, 'exception' => $exception]);
        }
    }

    private function isAutomated(Request $request): bool
    {
        $userAgent = (string) $request->headers->get('User-Agent', '');
        if ($this->botDetector->isBot($userAgent)) {
            return true;
        }
        $userAgent = strtolower($userAgent);
        foreach (self::AUTOMATED_AGENT_MARKERS as $marker) {
            if (str_contains($userAgent, $marker)) {
                return true;
            }
        }

        return false;
    }
}
