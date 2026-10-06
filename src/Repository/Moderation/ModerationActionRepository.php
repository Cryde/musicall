<?php declare(strict_types=1);

namespace App\Repository\Moderation;

use App\Entity\Moderation\ModerationAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModerationAction>
 */
class ModerationActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationAction::class);
    }
}
