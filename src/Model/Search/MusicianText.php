<?php

namespace App\Model\Search;

use Symfony\Component\Validator\Constraints as Assert;

class MusicianText
{
    #[Assert\Length(
        min: 10,
        max: 200,
        minMessage: 'Cette recherche est trop courte (min {{ limit }} caractères)',
        maxMessage: 'Cette recherche est trop longue (max {{ limit }} caractères)'
    )]
    private string $search;

    public function getSearch(): string
    {
        return $this->search;
    }

    public function setSearch(string $search): MusicianText
    {
        $this->search = $search;

        return $this;
    }
}