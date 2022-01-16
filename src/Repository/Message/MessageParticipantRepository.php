<?php

namespace App\Repository\Message;

use App\Entity\Message\MessageParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MessageParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageParticipant[]    findAll()
 * @method MessageParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageParticipant::class);
    }
}
