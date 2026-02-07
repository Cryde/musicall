<?php declare(strict_types=1);

namespace App\Contracts\Metric;

use App\Entity\Metric\VoteCache;

interface VotableInterface
{
    public function getVoteCache(): ?VoteCache;

    public function setVoteCache(?VoteCache $voteCache): self;

    public function getVotableId(): ?string;

    public function getVotableType(): string;
}
