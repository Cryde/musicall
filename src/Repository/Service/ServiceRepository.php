<?php

declare(strict_types=1);

namespace App\Repository\Service;

use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Repository\User\Relation\UserBlockRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findOneByUserAndKind(User $user, ServiceKind $kind): ?Service
    {
        return $this->findOneBy(['user' => $user, 'kind' => $kind]);
    }

    public function findOneByUsernameAndKind(string $username, ServiceKind $kind): ?Service
    {
        return $this->createQueryBuilder('service')
            ->innerJoin('service.user', 'u')
            ->where('u.username = :username')
            ->andWhere('service.kind = :kind')
            ->setParameter('username', $username)
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Service>
     */
    public function findRecentByKind(ServiceKind $kind, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit): array
    {
        return $this->createQueryBuilder('service')
            ->innerJoin('service.user', 'u')
            ->addSelect('u')
            ->where('service.kind = :kind')
            ->andWhere('service.creationDatetime >= :from')
            ->andWhere('service.creationDatetime < :to')
            ->setParameter('kind', $kind)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('service.creationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Service>
     */
    public function findAllWithInstrumentsByKind(ServiceKind $kind, ?User $viewer = null): array
    {
        $queryBuilder = $this->createQueryBuilder('service')
            ->innerJoin('service.user', 'u')
            ->addSelect('u')
            ->leftJoin('service.instruments', 'i')
            ->addSelect('i')
            ->leftJoin('i.instrument', 'instr')
            ->addSelect('instr')
            ->where('service.kind = :kind')
            ->andWhere(UserRepository::publiclyVisible('u'))
            ->setParameter('kind', $kind)
            ->orderBy('service.creationDatetime', 'ASC');
        if ($viewer instanceof User) {
            $queryBuilder->andWhere(UserBlockRepository::notBlockedEitherWay('u', 'viewer'))
                ->setParameter('viewer', $viewer);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
