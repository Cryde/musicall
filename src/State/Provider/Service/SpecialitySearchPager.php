<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\ApiResource\Service\Search\SpecialitySearchResult;
use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Model\Search\SpecialitySearch;
use App\Repository\Service\ServiceRepository;
use Symfony\Bundle\SecurityBundle\Security;

/** One page of a kind's search by speciality (#1410, #1411), each card built by the kind's own builder. */
readonly class SpecialitySearchPager
{
    public function __construct(
        private Security $security,
        private ServiceRepository $serviceRepository,
    ) {
    }

    /**
     * @template T of SpecialitySearchResult
     *
     * @param callable(Service, float|null, string|null): T $card the service, its distance in metres, the speciality searched
     *
     * @return TraversablePaginator<T>
     */
    public function page(Operation $operation, ServiceKind $kind, callable $card): TraversablePaginator
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;

        $query = ServiceSearchParameters::of($operation->getParameters());
        $search = new SpecialitySearch($kind);
        $speciality = $query->value('speciality');
        $search->speciality = is_string($speciality) && $speciality !== '' ? $speciality : null;
        $style = $query->value('style');
        $search->style = $style instanceof Style ? $style : null;
        $query->fill($search->service, SpecialitySearchResult::PAGE_SIZE);

        $page = $this->serviceRepository->findSpecialitySearchPage($search, $viewer);
        $services = $this->serviceRepository->findForSearchCards(array_column($page, 'id'), $kind);
        $results = [];
        foreach ($page as $row) {
            if (isset($services[$row['id']])) {
                $results[] = $card($services[$row['id']], $row['distance'], $search->speciality);
            }
        }

        $service = $search->service;
        $total = $service->total(count($page), fn (): int => $this->serviceRepository->countSpecialitySearch($search, $viewer));

        return new TraversablePaginator(new \ArrayIterator($results), $service->page, $service->limit, $total);
    }
}
