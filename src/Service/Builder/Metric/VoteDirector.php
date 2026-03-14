<?php declare(strict_types=1);

namespace App\Service\Builder\Metric;

use App\Entity\Metric\Vote;
use App\Entity\Metric\VoteCache;
use App\Entity\User;

class VoteDirector
{
    public function build(
        VoteCache $voteCache,
        string    $identifier,
        ?User     $user,
        int       $value,
        ?string   $entityType = null,
        ?string   $entityId = null,
    ): Vote {
        $vote = new Vote();
        $vote->voteCache = $voteCache;
        $vote->identifier = $identifier;
        $vote->user = $user;
        $vote->value = $value;
        $vote->entityType = $entityType;
        $vote->entityId = $entityId;

        return $vote;
    }
}
