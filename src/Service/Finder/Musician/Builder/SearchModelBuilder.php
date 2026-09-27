<?php declare(strict_types=1);

namespace App\Service\Finder\Musician\Builder;

use ApiPlatform\Metadata\Parameters;
use ApiPlatform\State\ParameterNotFound;
use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Model\Search\MusicianSearch;

class SearchModelBuilder
{
    /**
     * @param Style[] $styles
     */
    public function build(
        ?int        $searchType,
        ?Instrument $instrument,
        array       $styles,
        ?float      $longitude = null,
        ?float      $latitude = null,
        int         $page = 1,
        int         $limit = 12,
        ?int        $radius = null,
    ): MusicianSearch {
        $searchModel = new MusicianSearch();
        $searchModel->type = $searchType;
        $searchModel->instrument = $instrument;
        $searchModel->styles = $styles;
        $searchModel->longitude = $longitude;
        $searchModel->latitude = $latitude;
        $searchModel->page = $page;
        $searchModel->limit = $limit;
        $searchModel->radius = $radius;

        return $searchModel;
    }

    /**
     * The search the query parameters of a musician search describe, already validated by their
     * declarations: shared by the search and by the check of what a wider search would find (#1084).
     */
    public function buildFromParameters(?Parameters $params, int $limit): MusicianSearch
    {
        $value = static function (string $key) use ($params): mixed {
            $value = $params?->get($key)?->getValue();

            return $value instanceof ParameterNotFound ? null : $value;
        };

        $type = $value('type');
        $longitude = $value('longitude');
        $latitude = $value('latitude');
        $page = $value('page');
        $radius = $value('radius');

        return $this->build(
            $type !== null ? (int) $type : null,
            $value('instrument'),
            $value('styles') ?? [],
            $longitude !== null ? (float) $longitude : null,
            $latitude !== null ? (float) $latitude : null,
            $page !== null ? (int) $page : 1,
            $limit,
            $radius !== null ? (int) $radius : null,
        );
    }
}
