<?php declare(strict_types=1);

namespace App\Service\Finder\Musician\Builder;

use App\ApiResource\Search\AnnounceMusicianFilter;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;

class AnnounceMusicianFilterBuilder
{
    public function __construct(
        private InstrumentRepository $instrumentRepository,
        private StyleRepository      $styleRepository
    ) {
    }

    /**
     * @param array{type?: mixed, instrument?: string, styles?: string[], coordinates?: array{latitude?: float, longitude?: float}} $data
     */
    public function buildFromArray(array $data): ?AnnounceMusicianFilter
    {
        if (!isset($data['type'], $data['instrument'])) {
            return null;
        }
        if (!$instrumentId = $this->getInstrumentId($data['instrument'])) {
            return null;
        }
        $filter = new AnnounceMusicianFilter();
        $filter->type = (int)$data['type'];
        $filter->instrument = $instrumentId;
        $filter->styles = $this->getStyleIds($data['styles'] ?? []);
        if (isset($data['coordinates']['latitude'], $data['coordinates']['longitude'])) {
            $filter->latitude = $data['coordinates']['latitude'];
            $filter->longitude = $data['coordinates']['longitude'];
        }

        return $filter;
    }

    public function getInstrumentId(string $instrumentId): ?string
    {
        return $this->instrumentRepository->find($instrumentId)?->getId();
    }

    /**
     * @param string[] $styleIds
     *
     * @return string[]
     */
    public function getStyleIds(array $styleIds): array
    {
        return
            array_values(
                array_filter(
                    array_map(
                        fn(string $styleId): ?string => $this->styleRepository->find($styleId)?->getId(),
                        $styleIds
                    )
                )
            );
    }
}
