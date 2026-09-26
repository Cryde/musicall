<?php

declare(strict_types=1);

namespace App\State\Provider\Admin\Search;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\Search\AdminAiSearch;
use App\Entity\Attribute\Style;
use App\Entity\Search\MusicianSearchLog;
use App\Repository\Attribute\StyleRepository;
use App\Repository\Search\MusicianSearchLogRepository;
use ArrayIterator;

/**
 * @implements ProviderInterface<AdminAiSearch>
 */
readonly class AdminAiSearchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MusicianSearchLogRepository $musicianSearchLogRepository,
        private StyleRepository $styleRepository,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return TraversablePaginator<AdminAiSearch>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $paginator = $this->musicianSearchLogRepository->findAiSearchesForAdmin(
            $this->pagination->getOffset($operation, $context),
            $itemsPerPage,
        );

        /** @var list<MusicianSearchLog> $logs */
        $logs = array_values(iterator_to_array($paginator));
        $styleNames = $this->styleNamesById($logs);

        $searches = array_map(static function (MusicianSearchLog $log) use ($styleNames): AdminAiSearch {
            $search = new AdminAiSearch();
            $search->id = (string) $log->id;
            $search->query = (string) $log->aiQuery;
            $search->outcome = (string) $log->aiOutcome?->value;
            $search->type = $log->type;
            $search->instrumentName = $log->instrument?->musicianName;
            // A style deleted since is simply left out.
            $search->styleNames = array_values(array_filter(array_map(
                static fn (string $id): ?string => $styleNames[$id] ?? null,
                $log->styleIds,
            )));
            $search->latitude = $log->latitude;
            $search->longitude = $log->longitude;
            $search->authenticated = $log->authenticated;
            $search->searchDatetime = $log->searchDatetime;

            return $search;
        }, $logs);

        return new TraversablePaginator(new ArrayIterator($searches), $page, $itemsPerPage, count($paginator));
    }

    /**
     * One query for the styles of the whole page.
     *
     * @param list<MusicianSearchLog> $logs
     *
     * @return array<string, string>
     */
    private function styleNamesById(array $logs): array
    {
        $ids = array_values(array_unique(array_merge([], ...array_map(static fn (MusicianSearchLog $log): array => $log->styleIds, $logs))));
        if ($ids === []) {
            return [];
        }

        $names = [];
        foreach ($this->styleRepository->findBy(['id' => $ids]) as $style) {
            /** @var Style $style */
            $names[(string) $style->id] = $style->name;
        }

        return $names;
    }
}
