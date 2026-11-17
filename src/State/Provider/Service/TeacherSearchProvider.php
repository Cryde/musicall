<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\TeacherSearchResult;
use App\Entity\Attribute\Instrument;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\TeachingSearchOrder;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\StudentLevel;
use App\Model\Search\TeachingSearch;
use App\Repository\Service\ServiceRepository;
use App\Service\Builder\Service\TeacherSearchResultBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<TeacherSearchResult>
 */
readonly class TeacherSearchProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceRepository $serviceRepository,
        private TeacherSearchResultBuilder $teacherSearchResultBuilder,
    ) {
    }

    /**
     * @return TraversablePaginator<TeacherSearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        $search = $this->searchOf($operation->getParameters());

        $page = $this->serviceRepository->findTeachingSearchPage($search, $viewer);
        $services = $this->serviceRepository->findForSearchCards(array_column($page, 'id'), ServiceKind::Teaching);
        $results = [];
        foreach ($page as $row) {
            if (isset($services[$row['id']])) {
                $results[] = $this->teacherSearchResultBuilder->build($services[$row['id']], $row['distance'], $search->instrument);
            }
        }

        $service = $search->service;
        $total = $service->total(count($page), fn (): int => $this->serviceRepository->countTeachingSearch($search, $viewer));

        return new TraversablePaginator(new \ArrayIterator($results), $service->page, $service->limit, $total);
    }

    /** The search its query parameters describe, already validated by their declarations. */
    private function searchOf(?Parameters $parameters): TeachingSearch
    {
        $query = ServiceSearchParameters::of($parameters);

        $search = new TeachingSearch();
        $instrument = $query->value('instrument');
        $search->instrument = $instrument instanceof Instrument ? $instrument : null;
        $search->level = StudentLevel::tryFrom((string) $query->value('level'));
        $search->ageGroup = AgeGroup::tryFrom((string) $query->value('age_group'));
        $search->freeTrial = $query->value('free_trial') === '1';
        $search->order = TeachingSearchOrder::tryFrom((string) $query->value('order'));
        $query->fill($search->service, TeacherSearchResult::PAGE_SIZE);

        return $search;
    }
}
