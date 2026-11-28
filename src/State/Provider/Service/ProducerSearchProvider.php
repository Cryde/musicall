<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\ProducerSearchResult;
use App\Entity\Service\Service;
use App\Enum\Service\ServiceKind;
use App\Service\Builder\Service\ProducerSearchResultBuilder;

/**
 * @implements ProviderInterface<ProducerSearchResult>
 */
readonly class ProducerSearchProvider implements ProviderInterface
{
    public function __construct(
        private SpecialitySearchPager $pager,
        private ProducerSearchResultBuilder $resultBuilder,
    ) {
    }

    /**
     * @return TraversablePaginator<ProducerSearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        return $this->pager->page(
            $operation,
            ServiceKind::Production,
            fn (Service $service, ?float $distance, ?string $speciality): ProducerSearchResult => $this->resultBuilder->build($service, $distance, $speciality),
        );
    }
}
