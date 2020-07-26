<?php

namespace App\Serializer\Search;

use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Serializer\User\UserSearchArraySerializer;
use Doctrine\Common\Collections\Collection;

class MusicianSearchArraySerializer
{
    private UserSearchArraySerializer $userSearchArraySerializer;
    private \HTMLPurifier $onlybrPurifier;

    public function __construct(UserSearchArraySerializer $userSearchArraySerializer, \HTMLPurifier $onlybrPurifier)
    {
        $this->userSearchArraySerializer = $userSearchArraySerializer;
        $this->onlybrPurifier = $onlybrPurifier;
    }

    public function listToArray($list)
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
            'note'          => $this->onlybrPurifier->purify(nl2br($musicianAnnounce->getNote())),
            'user'          => $this->userSearchArraySerializer->toArray($musicianAnnounce->getAuthor()),
            'instrument'    => $musicianAnnounce->getInstrument()->getName(),
            'type'          => $musicianAnnounce->getType(),
            'styles'        => $this->formatStyle($musicianAnnounce->getStyles()),
        ];
    }

    /**
     * @param Style[]|Collection $styles
     *
     * @return string
     */
    private function formatStyle($styles)
    {
        return implode(', ', $styles->map(fn(Style $item) => $item->getName())->toArray());
    }
}
