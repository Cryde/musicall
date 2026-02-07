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
        return (new Vote())
            ->setVoteCache($voteCache)
            ->setIdentifier($identifier)
            ->setUser($user)
            ->setValue($value)
            ->setEntityType($entityType)
            ->setEntityId($entityId);
    }
}
