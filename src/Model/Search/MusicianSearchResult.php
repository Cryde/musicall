<?php

namespace App\Model\Search;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

class MusicianSearchResult
{
    public const LIST = 'MUSICIAN_SEARCH_RESULT_LIST';
    #[Groups([MusicianSearchResult::LIST])]
    public string $id;
    #[Groups([MusicianSearchResult::LIST])]
    public ?string $locationName = null;
    #[Groups([MusicianSearchResult::LIST])]
    public ?string $note = null;
    #[Groups([MusicianSearchResult::LIST])]
    public User $user;
    #[Groups([MusicianSearchResult::LIST])]
    public Instrument $instrument;
    #[Groups([MusicianSearchResult::LIST])]
    public int $type;
    #[Groups([MusicianSearchResult::LIST])]
    /** @var Style[]|Collection */
    public $styles;
    #[Groups([MusicianSearchResult::LIST])]
    public ?float $distance = null;
}