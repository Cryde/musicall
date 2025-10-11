<?php

namespace App\ApiResource\Search;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Search\MusicianSearchProvider;
use Symfony\Component\Validator\Constraints as Assert;


class MusicianText
{
    #[Assert\Length(
        min: 10,
        max: 200,
        minMessage: 'Cette recherche est trop courte (min {{ limit }} caractères)',
        maxMessage: 'Cette recherche est trop longue (max {{ limit }} caractères)'
    )]
    public string $search;

    public function setSearch(string $search): MusicianText
    {
        $this->search = $search;

        return $this;
    }
}
