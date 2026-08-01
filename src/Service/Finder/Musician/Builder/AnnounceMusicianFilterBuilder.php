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
     * @param array{type?: mixed, instrument?: string|null, styles?: string[], coordinates?: array{latitude?: float, longitude?: float}|null} $data
     */
    public function buildFromArray(array $data): ?AnnounceMusicianFilter
    {
        if (!isset($data['type'])) {
            return null;
        }

        $filter = new AnnounceMusicianFilter();
        $filter->type = (int) $data['type'];
        // Only the type is load bearing. An unresolved instrument or style widens the search instead of
        // killing it: returning null here would show the user "no result" for a perfectly clear query.
        $filter->instrument = isset($data['instrument']) ? $this->getInstrumentId($data['instrument']) : null;
        $filter->styles = $this->getStyleIds($data['styles'] ?? []);
        if (isset($data['coordinates']['latitude'], $data['coordinates']['longitude'])) {
            $filter->latitude = $data['coordinates']['latitude'];
            $filter->longitude = $data['coordinates']['longitude'];
        }

        return $filter;
    }

    /**
     * Accepts an id or a slug. The prompt hands the model a map of id => slug and it sometimes answers
     * with the slug, which is a good enough answer to honour rather than discard.
     */
    public function getInstrumentId(?string $instrument): ?string
    {
        if ($instrument === null || trim($instrument) === '') {
            return null;
        }

        $id = $this->isUuid($instrument)
            ? $this->instrumentRepository->find($instrument)?->id
            : $this->instrumentRepository->findOneBy(['slug' => $instrument])?->id;

        return $id !== null ? (string) $id : null;
    }

    /**
     * @param string[] $styleIds
     *
     * @return string[]
     */
    public function getStyleIds(array $styleIds): array
    {
        return array_values(array_unique(array_filter(
            array_map($this->getStyleId(...), $styleIds),
        )));
    }

    private function getStyleId(string $style): ?string
    {
        if (trim($style) === '') {
            return null;
        }

        $id = $this->isUuid($style)
            ? $this->styleRepository->find($style)?->id
            : $this->styleRepository->findOneBy(['slug' => $style])?->id;

        return $id !== null ? (string) $id : null;
    }

    /**
     * Guards the repository lookups: the id columns are UUID typed, so handing Doctrine a plain word
     * throws ValueNotConvertible, which would surface as a 500 rather than a degraded search.
     */
    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
