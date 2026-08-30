<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\BandSpaceSearchResult;
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

        $search = mb_strtolower(trim($this->requestStack->getCurrentRequest()?->query->getString('q') ?? ''));
        if (mb_strlen($search) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        // Keyed and ordered by BandSpaceSearchResultType, which is the order the palette groups in.
        // Should per-module permissions ever land (#785), this map is where they have to be applied:
        // a palette is precisely the surface that leaks a module a member may not open.
        $groups = [
            BandSpaceSearchResultType::Agenda->value => array_map(
                $this->builder->buildFromAgendaEntry(...),
                $this->agendaEntryRepository->searchByBandSpace($bandSpace, $search, self::PER_TYPE_LIMIT),
            ),
            BandSpaceSearchResultType::Task->value => array_map(
                $this->builder->buildFromTask(...),
                $this->taskRepository->searchByBandSpace($bandSpace, $search, self::PER_TYPE_LIMIT),
            ),
            BandSpaceSearchResultType::Note->value => array_map(
                $this->builder->buildFromNote(...),
                $this->noteRepository->searchByBandSpace($bandSpace, $search, self::PER_TYPE_LIMIT),
            ),
            BandSpaceSearchResultType::File->value => array_map(
                $this->builder->buildFromFile(...),
                $this->fileRepository->searchByBandSpace($bandSpace, $search, self::PER_TYPE_LIMIT),
            ),
            BandSpaceSearchResultType::Setlist->value => array_map(
                $this->builder->buildFromSetlist(...),
                $this->setlistRepository->searchByBandSpace($bandSpace, $search, self::PER_TYPE_LIMIT),
            ),
            BandSpaceSearchResultType::Song->value => array_map(
                $this->builder->buildFromSong(...),
                $this->songRepository->searchByBandSpace($bandSpace, $search, self::PER_TYPE_LIMIT),
            ),
            // The viewer is load bearing: a personal finance entry belongs to the member it names.
            BandSpaceSearchResultType::Finance->value => array_map(
                $this->builder->buildFromFinanceEntry(...),
                $this->financeEntryRepository->searchByBandSpace($bandSpace, $viewer, $search, self::PER_TYPE_LIMIT),
            ),
        ];

        return $this->trimToTotalCap($groups);
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
