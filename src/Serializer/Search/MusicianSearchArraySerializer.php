<?php

namespace App\Serializer\Search;

use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Serializer\User\UserArraySerializer;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class MusicianSearchArraySerializer
{
    public function __construct(
        private readonly UserArraySerializer    $userArraySerializer,
        private readonly HtmlSanitizerInterface $appOnlybrSanitizer
    ) {
    }

    public function listToArray($list): array
    {
        $result = [];
        foreach ($list as $item) {
            if (is_array($item)) {
                $result[] = array_merge($this->toArray($item[0]), ['distance' => $item['distance']]);
            } else {
                $result[] = $this->toArray($item);
            }
        }

        return $result;
    }

    public function toArray(MusicianAnnounce $musicianAnnounce): array
    {
        return [
            'id'            => $musicianAnnounce->getId(),
            'location_name' => $musicianAnnounce->getLocationName(),
            'note'          => $this->appOnlybrSanitizer->sanitize(nl2br($musicianAnnounce->getNote())),
            'user'          => $this->userArraySerializer->toArray($musicianAnnounce->getAuthor()),
            'instrument'    => $musicianAnnounce->getInstrument()->getName(),
            'type'          => $musicianAnnounce->getType(),
            'styles'        => $this->formatStyle($musicianAnnounce->getStyles()),
        ];
    }

    /**
     * @param Collection|Style[] $styles
     */
    private function formatStyle(Collection|array $styles): string
    {
        return implode(', ', $styles->map(fn(Style $item) => $item->getName())->toArray());
    }
}
