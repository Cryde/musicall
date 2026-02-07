<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use Symfony\Component\Validator\Constraints as Assert;

class PublicationVoteInput
{
    #[Assert\NotNull(message: 'La valeur du vote est obligatoire.')]
    #[Assert\Choice(choices: [1, -1], message: 'La valeur du vote doit être 1 ou -1.')]
    public int $userVote;
}
