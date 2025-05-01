<?php

namespace App\Service\Builder\Search;

use App\ApiResource\Search\MusicianSearchResult;
use App\Entity\Musician\MusicianAnnounce;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class MusicianSearchResultBuilder
{
    public function __construct(private readonly HtmlSanitizerInterface $appOnlybrSanitizer)
    {
    }

    public function buildFromList(array $list): array
    {
        $result = [];
        foreach ($list as $item) {
            $result[] = is_array($item) ? $this->build($item[0], $item['distance']) : $this->build($item);
        }

        return $result;
    }

    public function build(MusicianAnnounce $musicianAnnounce, ?float $distance = null): MusicianSearchResult
    {
        $musicianSearchResult = new MusicianSearchResult();
        $musicianSearchResult->id = $musicianAnnounce->getId();
        $musicianSearchResult->user = $musicianAnnounce->getAuthor();
        $musicianSearchResult->instrument = $musicianAnnounce->getInstrument();
        $musicianSearchResult->styles = $musicianAnnounce->getStyles();
        $musicianSearchResult->note = $this->appOnlybrSanitizer->sanitize($musicianAnnounce->getNote());
        $musicianSearchResult->locationName = $musicianAnnounce->getLocationName();
        $musicianSearchResult->type = $musicianAnnounce->getType();
        if ($distance) {
            $musicianSearchResult->distance = $distance;
        }

        return $musicianSearchResult;
    }
}