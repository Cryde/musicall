<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\SoundEngineerSearchResult;
use App\Entity\Service\Service;
use App\Enum\Service\ServiceKind;
use App\Service\Builder\Service\SoundEngineerSearchResultBuilder;

/**
 * @implements ProviderInterface<SoundEngineerSearchResult>
 */
readonly class SoundEngineerSearchProvider implements ProviderInterface
{
    public function __construct(
        private SpecialitySearchPager $pager,
        private SoundEngineerSearchResultBuilder $resultBuilder,
    ) {
    }

    /**
     * @return TraversablePaginator<SoundEngineerSearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        return $this->pager->page(
            $operation,
            ServiceKind::SoundEngineering,
            fn (Service $service, ?float $distance, ?string $speciality): SoundEngineerSearchResult => $this->resultBuilder->build($service, $distance, $speciality),
        );
    }
}
