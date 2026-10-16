<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use Symfony\Component\Validator\Constraints as Assert;

class PublicationVoteInput
{
    #[Assert\NotNull(message: 'common.vote.required')]
    #[Assert\Choice(choices: [1, -1], message: 'common.vote.invalid_value')]
    public int $userVote;
}
