<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\TeacherSearchResult;
use App\Entity\Attribute\Instrument;
use App\Entity\User;
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
        $services = $this->serviceRepository->findForSearchCards(array_column($page, 'id'));
        $results = [];
        foreach ($page as $row) {
            if (isset($services[$row['id']])) {
                $results[] = $this->teacherSearchResultBuilder->build($services[$row['id']], $row['distance'], $search->instrument);
            }
        }

        $service = $search->service;
        // A short page that is not past the end is the last one: it tells the total without counting.
        $total = count($page) < $service->limit && ($page !== [] || $service->page === 1)
            ? $service->offset() + count($page)
            : $this->serviceRepository->countTeachingSearch($search, $viewer);

        return new TraversablePaginator(new \ArrayIterator($results), $service->page, $service->limit, $total);
    }

    /** The search its query parameters describe, already validated by their declarations. */
    private function searchOf(?Parameters $parameters): TeachingSearch
    {
        $value = static function (string $key) use ($parameters): mixed {
            $value = $parameters?->get($key)?->getValue();

            return $value instanceof ParameterNotFound ? null : $value;
        };

        $search = new TeachingSearch();
        $instrument = $value('instrument');
        $search->instrument = $instrument instanceof Instrument ? $instrument : null;
        $search->level = StudentLevel::tryFrom((string) $value('level'));
        $search->ageGroup = AgeGroup::tryFrom((string) $value('age_group'));
        $search->freeTrial = $value('free_trial') === '1';
        $search->order = TeachingSearchOrder::tryFrom((string) $value('order'));

        $service = $search->service;
        $service->limit = TeacherSearchResult::PAGE_SIZE;
        $latitude = $value('lat');
        $longitude = $value('lng');
        // A point needs both: one alone says nowhere.
        if (is_numeric($latitude) && is_numeric($longitude)) {
            $service->latitude = (float) $latitude;
            $service->longitude = (float) $longitude;
        }
        $radius = $value('radius');
        $service->radius = is_numeric($radius) ? (int) $radius : null;
        $service->online = $value('online') === '1';
        $page = $value('page');
        $service->page = is_numeric($page) ? (int) $page : 1;

        return $search;
    }
}
