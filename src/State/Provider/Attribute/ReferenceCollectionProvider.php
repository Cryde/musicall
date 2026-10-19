<?php

declare(strict_types=1);

namespace App\State\Provider\Attribute;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Translation\ReferenceNameSorter;
use App\Translation\ReferenceNameTranslator;

/**
 * The instrument and style lists, which the operation's `order:` sorts by the French name, re-sorted by the name the
 * request locale shows. Only the page is re-sorted, so past 100 records each page would be sorted on its own: fine for
 * about 20 instruments and 99 styles, and the front end only ever reads page 1.
 *
 * @implements ProviderInterface<Instrument|Style>
 */
readonly class ReferenceCollectionProvider implements ProviderInterface
{
    public function __construct(
        private CollectionProvider $collectionProvider,
        private ReferenceNameSorter $sorter,
        private ReferenceNameTranslator $referenceNames,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var PaginatorInterface<Instrument|Style> $paginator */
        $paginator = $this->collectionProvider->provide($operation, $uriVariables, $context);
        $records = array_values(iterator_to_array($paginator));

        return new TraversablePaginator(
            new \ArrayIterator($this->sorter->sort($records, $this->shownName(...))),
            $paginator->getCurrentPage(),
            $paginator->getItemsPerPage(),
            $paginator->getTotalItems(),
        );
    }

    /** The name each list is ordered by in the database, translated. */
    private function shownName(Instrument|Style $record): string
    {
        return $record instanceof Instrument
            ? $this->referenceNames->instrumentMusicianName($record)
            : $this->referenceNames->style($record);
    }
}
