<?php declare(strict_types=1);

namespace App\Service\Finder\Musician\Builder;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Model\Search\MusicianSearch;

class SearchModelBuilder
{
    /**
     * @param Style[] $styles
     */
    public function build(
        int        $searchType,
        Instrument $instrument,
        array      $styles,
        ?float     $longitude = null,
        ?float     $latitude = null,
    ): MusicianSearch {
        $searchModel = new MusicianSearch();
        $searchModel->type = $searchType;
        $searchModel->instrument = $instrument;
        $searchModel->styles = $styles;
        $searchModel->longitude = $longitude;
        $searchModel->latitude = $latitude;

        return $searchModel;
    }
}
