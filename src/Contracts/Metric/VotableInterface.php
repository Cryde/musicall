<?php declare(strict_types=1);

namespace App\Contracts\Metric;

use App\Entity\Metric\VoteCache;

interface VotableInterface
{
    public ?VoteCache $voteCache { get; set; }

    public function getVotableId(): ?string;

    public function getVotableType(): string;
}
