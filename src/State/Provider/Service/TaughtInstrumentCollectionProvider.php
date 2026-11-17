<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Search\TaughtInstrument;
use App\Entity\Attribute\Instrument;
use App\Repository\Service\ServiceRepository;
use App\Translation\ReferenceNameSorter;
use App\Translation\ReferenceNameTranslator;

/**
 * @implements ProviderInterface<TaughtInstrument>
 */
readonly class TaughtInstrumentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private ReferenceNameTranslator $referenceNames,
        private ReferenceNameSorter $sorter,
    ) {
    }

    /** @return list<TaughtInstrument> in the order of the names the request locale shows */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $instruments = $this->sorter->sort($this->serviceRepository->findTaughtInstruments(), $this->referenceNames->instrument(...));

        return array_map(function (Instrument $instrument): TaughtInstrument {
            $taught = new TaughtInstrument();
            $taught->id = (string) $instrument->id;
            $taught->name = $this->referenceNames->instrument($instrument);
            $taught->slug = $instrument->slug;

            return $taught;
        }, $instruments);
    }
}
