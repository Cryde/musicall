<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\BandSpaceSearchResult;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Repository\BandSpace\SongRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\BandSpaceSearchResultBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The command palette's single endpoint: one LIKE query per module, merged and grouped by record
 * kind. Elasticsearch would be the wrong tool at this size, and would add an index lifecycle and a
 * consistency window to a feature that answers in milliseconds without them.
 *
 * @implements ProviderInterface<BandSpaceSearchResult>
 */
readonly class BandSpaceSearchProvider implements ProviderInterface
{
    /**
     * A single character scans every module to say nothing useful. Below this the endpoint answers
     * with an empty collection rather than a 422: the palette searches while the member types, and
     * an error on the first keystroke of every search would be noise, not feedback.
     */
    private const int MIN_QUERY_LENGTH = 2;

    private const int PER_TYPE_LIMIT = 5;

    private const int TOTAL_LIMIT = 20;

    /** How many recent items the palette opens on (#1046), across every kind or of the one picked. */
    private const int RECENT_LIMIT = 5;

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceSearchResultBuilder $builder,
        private AgendaEntryRepository $agendaEntryRepository,
        private TaskRepository $taskRepository,
        private BandSpaceNoteRepository $noteRepository,
        private BandSpaceFileRepository $fileRepository,
        private SetlistRepository $setlistRepository,
        private SongRepository $songRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return BandSpaceSearchResult[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // Once, not once per module. Everything below may assume the space is already authorised.
        [$bandSpace, $viewer] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $query = $this->requestStack->getCurrentRequest()?->query;
        $search = mb_strtolower(trim($query?->getString('q') ?? ''));
        // Already one of the enum's values when sent: the operation's Assert\Choice refused anything else.
        $onlyType = BandSpaceSearchResultType::tryFrom($query?->getString('type') ?? '');
        $types = $onlyType instanceof BandSpaceSearchResultType ? [$onlyType] : BandSpaceSearchResultType::cases();

        if (mb_strlen($search) < self::MIN_QUERY_LENGTH) {
            return $this->recent($bandSpace, $viewer, $types);
        }

        // With one kind picked there is no budget to share, and picking it is asking to see more of it.
        $perTypeLimit = $onlyType instanceof BandSpaceSearchResultType ? self::TOTAL_LIMIT : self::PER_TYPE_LIMIT;

        $groups = [];
        foreach ($types as $type) {
            $groups[$type->value] = array_map(
                fn (object $entity): BandSpaceSearchResult => $this->build($type, $entity),
                $this->search($type, $bandSpace, $viewer, $search, $perTypeLimit),
            );
        }

        return $this->trimToTotalCap($groups);
    }

    /**
     * What the palette opens on before anything is typed (#1046): the items the band most recently
     * created or edited, newest first across the kinds asked for. One small query per kind, then a
     * merge on the same date each one was ordered by.
     *
     * @param BandSpaceSearchResultType[] $types
     *
     * @return BandSpaceSearchResult[]
     */
    private function recent(BandSpace $bandSpace, BandSpaceMembership $viewer, array $types): array
    {
        $candidates = [];
        foreach ($types as $type) {
            foreach ($this->findRecent($type, $bandSpace, $viewer) as $entity) {
                $candidates[] = ['recency' => $this->recencyOf($entity), 'result' => $this->build($type, $entity)];
            }
        }

        // Newest first, then the result id, so a tie between two kinds reads the same on every call,
        // as the per-kind queries break theirs on the id.
        usort($candidates, static fn (array $a, array $b): int => [$b['recency'], $b['result']->id] <=> [$a['recency'], $a['result']->id]);

        return array_column(array_slice($candidates, 0, self::RECENT_LIMIT), 'result');
    }

    /**
     * One entry per kind, so there is a single place deciding what each kind may show. Should
     * per-module permissions ever land (#785), this is where they have to be applied, search and
     * recents alike: a palette is precisely the surface that leaks a module a member may not open.
     *
     * @return object[]
     */
    private function search(BandSpaceSearchResultType $type, BandSpace $bandSpace, BandSpaceMembership $viewer, string $search, int $limit): array
    {
        return match ($type) {
            BandSpaceSearchResultType::Agenda => $this->agendaEntryRepository->searchByBandSpace($bandSpace, $search, $limit),
            BandSpaceSearchResultType::Task => $this->taskRepository->searchByBandSpace($bandSpace, $search, $limit),
            BandSpaceSearchResultType::Note => $this->noteRepository->searchByBandSpace($bandSpace, $search, $limit),
            BandSpaceSearchResultType::File => $this->fileRepository->searchByBandSpace($bandSpace, $search, $limit),
            BandSpaceSearchResultType::Setlist => $this->setlistRepository->searchByBandSpace($bandSpace, $search, $limit),
            BandSpaceSearchResultType::Song => $this->songRepository->searchByBandSpace($bandSpace, $search, $limit),
            // The viewer is load bearing: a personal finance entry belongs to the member it names.
            BandSpaceSearchResultType::Finance => $this->financeEntryRepository->searchByBandSpace($bandSpace, $viewer, $search, $limit),
        };
    }

    /**
     * @return object[]
     */
    private function findRecent(BandSpaceSearchResultType $type, BandSpace $bandSpace, BandSpaceMembership $viewer): array
    {
        return match ($type) {
            BandSpaceSearchResultType::Agenda => $this->agendaEntryRepository->findRecentByBandSpace($bandSpace, self::RECENT_LIMIT),
            BandSpaceSearchResultType::Task => $this->taskRepository->findRecentByBandSpace($bandSpace, self::RECENT_LIMIT),
            BandSpaceSearchResultType::Note => $this->noteRepository->findRecentByBandSpace($bandSpace, self::RECENT_LIMIT),
            BandSpaceSearchResultType::File => $this->fileRepository->findRecentByBandSpace($bandSpace, self::RECENT_LIMIT),
            BandSpaceSearchResultType::Setlist => $this->setlistRepository->findRecentByBandSpace($bandSpace, self::RECENT_LIMIT),
            BandSpaceSearchResultType::Song => $this->songRepository->findRecentByBandSpace($bandSpace, self::RECENT_LIMIT),
            BandSpaceSearchResultType::Finance => $this->financeEntryRepository->findRecentByBandSpace($bandSpace, $viewer, self::RECENT_LIMIT),
        };
    }

    private function build(BandSpaceSearchResultType $type, object $entity): BandSpaceSearchResult
    {
        return match (true) {
            $type === BandSpaceSearchResultType::Agenda && $entity instanceof AgendaEntry => $this->builder->buildFromAgendaEntry($entity),
            $type === BandSpaceSearchResultType::Task && $entity instanceof Task => $this->builder->buildFromTask($entity),
            $type === BandSpaceSearchResultType::Note && $entity instanceof BandSpaceNote => $this->builder->buildFromNote($entity),
            $type === BandSpaceSearchResultType::File && $entity instanceof BandSpaceFile => $this->builder->buildFromFile($entity),
            $type === BandSpaceSearchResultType::Setlist && $entity instanceof Setlist => $this->builder->buildFromSetlist($entity),
            $type === BandSpaceSearchResultType::Song && $entity instanceof Song => $this->builder->buildFromSong($entity),
            $type === BandSpaceSearchResultType::Finance && $entity instanceof FinanceEntry => $this->builder->buildFromFinanceEntry($entity),
            default => throw new \LogicException(sprintf('A %s result cannot be built from %s', $type->value, $entity::class)),
        };
    }

    /** The date every recents query orders on: the last edit, or the creation before the first one. */
    private function recencyOf(object $entity): \DateTimeInterface
    {
        if (!property_exists($entity, 'creationDatetime')) {
            throw new \LogicException(sprintf('%s has no creation date to sort on', $entity::class));
        }

        return property_exists($entity, 'updateDatetime') && $entity->updateDatetime instanceof \DateTimeInterface
            ? $entity->updateDatetime
            : $entity->creationDatetime;
    }

    /**
     * Round robin rather than truncating in type order. Only a broad query reaches the total cap at
     * all, since each type is already capped at PER_TYPE_LIMIT, and that is exactly the query where
     * truncating in order would spend the whole budget on agenda and tasks and never show finances.
     *
     * @param array<string, BandSpaceSearchResult[]> $groups
     * @return BandSpaceSearchResult[]
     */
    private function trimToTotalCap(array $groups): array
    {
        if (array_sum(array_map('count', $groups)) <= self::TOTAL_LIMIT) {
            return array_merge([], ...array_values($groups));
        }

        $shares = array_fill_keys(array_keys($groups), 0);
        $budget = self::TOTAL_LIMIT;
        $progressed = true;

        while ($budget > 0 && $progressed) {
            $progressed = false;
            foreach ($groups as $type => $results) {
                if ($budget === 0) {
                    break;
                }
                if ($shares[$type] >= count($results)) {
                    continue;
                }
                ++$shares[$type];
                --$budget;
                $progressed = true;
            }
        }

        $trimmed = [];
        foreach ($groups as $type => $results) {
            $trimmed[] = array_slice($results, 0, $shares[$type]);
        }

        return array_merge([], ...$trimmed);
    }
}
