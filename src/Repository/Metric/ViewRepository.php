<?php

namespace App\Repository\Metric;

use App\Entity\Metric\View;
use App\Entity\Metric\ViewCache;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method View|null find($id, $lockMode = null, $lockVersion = null)
 * @method View|null findOneBy(array $criteria, array $orderBy = null)
 * @method View[]    findAll()
 * @method View[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, View::class);
    }

    public function findOneByUser(ViewCache $viewCache, User $user)
    {
        return $this->createQueryBuilder('view')
            ->where('view.viewCache = :view_cache')
            ->andWhere('view.user = :user')
            ->setParameter('view_cache', $viewCache)
            ->setParameter('user', $user)
            ->getQuery()
            ->setMaxResults(1)
            ->getResult()[0] ?? null;
    }

    public function findOneByIdentifierAndPeriod(ViewCache $viewCache, string $identifier, \DateTime $dateTime)
    {
        return $this->createQueryBuilder('view')
            ->where('view.viewCache = :view_cache')
            ->andWhere('view.identifier = :identifier')
            ->andWhere('view.creationDatetime > :datetime')
            ->setParameter('view_cache', $viewCache)
            ->setParameter('identifier', $identifier)
            ->setParameter('datetime', $dateTime)
            ->getQuery()
            ->setMaxResults(1)
            ->getResult()[0] ?? null;
    }
}
