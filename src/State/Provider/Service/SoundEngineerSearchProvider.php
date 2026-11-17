<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\SoundEngineerSearchResult;
use App\Entity\Attribute\Style;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\SoundEngineeringSpeciality;
use App\Model\Search\SoundEngineeringSearch;
use App\Repository\Service\ServiceRepository;
use App\Service\Builder\Service\SoundEngineerSearchResultBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<SoundEngineerSearchResult>
 */
readonly class SoundEngineerSearchProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceRepository $serviceRepository,
        private SoundEngineerSearchResultBuilder $resultBuilder,
    ) {
    }

    /**
     * @return TraversablePaginator<SoundEngineerSearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;

        $query = ServiceSearchParameters::of($operation->getParameters());
        $search = new SoundEngineeringSearch();
        $search->speciality = SoundEngineeringSpeciality::tryFrom((string) $query->value('speciality'));
        $style = $query->value('style');
        $search->style = $style instanceof Style ? $style : null;
        $query->fill($search->service, SoundEngineerSearchResult::PAGE_SIZE);

        $page = $this->serviceRepository->findSoundEngineeringSearchPage($search, $viewer);
        $services = $this->serviceRepository->findForSearchCards(array_column($page, 'id'), ServiceKind::SoundEngineering);
        $results = [];
        foreach ($page as $row) {
            if (isset($services[$row['id']])) {
                $results[] = $this->resultBuilder->build($services[$row['id']], $row['distance'], $search->speciality);
            }
        }

        $service = $search->service;
        $total = $service->total(count($page), fn (): int => $this->serviceRepository->countSoundEngineeringSearch($search, $viewer));

        return new TraversablePaginator(new \ArrayIterator($results), $service->page, $service->limit, $total);
    }
}
