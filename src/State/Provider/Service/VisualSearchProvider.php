<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\VisualSearchResult;
use App\Entity\Service\Service;
use App\Service\Builder\Service\VisualSearchResultBuilder;

/**
 * The photographer or videographer search (#1411), the kind named by the operation's resource.
 *
 * @implements ProviderInterface<VisualSearchResult>
 */
readonly class VisualSearchProvider implements ProviderInterface
{
    public function __construct(
        private SpecialitySearchPager $pager,
        private VisualSearchResultBuilder $resultBuilder,
    ) {
    }

    /**
     * @return TraversablePaginator<VisualSearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var class-string<VisualSearchResult> $resourceClass */
        $resourceClass = $operation->getClass();

        return $this->pager->page(
            $operation,
            $resourceClass::kind(),
            fn (Service $service, ?float $distance, ?string $speciality): VisualSearchResult => $this->resultBuilder->build($service, new $resourceClass(), $distance, $speciality),
        );
    }
}
