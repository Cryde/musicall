<?php

declare(strict_types=1);

namespace App\Repository\Service;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceMedia;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Service\Profile\Wall\WallCursor;
use App\Service\Profile\Wall\WallItemKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceMedia>
 */
class ServiceMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceMedia::class);
    }

    public function getNextPosition(Service $service): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.position)')
            ->where('m.service = :service')
            ->setParameter('service', $service)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result + 1 : 0;
    }

    public function countByService(string $serviceId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.service = :serviceId')
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The media a member added to their published service of this kind, for their wall (#1316): newest first, past `$before`.
     *
     * @return list<ServiceMedia>
     */
    public function findForWall(User $owner, ServiceKind $kind, ?WallCursor $before, int $limit): array
    {
        $query = $this->createQueryBuilder('media')
            ->innerJoin('media.service', 'service')
            ->where('service.user = :owner')
            ->andWhere('service.kind = :kind')
            ->andWhere(ServiceRepository::published('service'))
            ->setParameter('owner', $owner)
            ->setParameter('kind', $kind)
            ->orderBy('media.creationDatetime', 'DESC')
            ->addOrderBy('media.id', 'DESC')
            ->setMaxResults($limit);
        $before?->applyTo($query, WallItemKind::TeacherMedia, 'media.creationDatetime', 'media.id');

        /** @var list<ServiceMedia> $media */
        $media = $query->getQuery()->getResult();

        return $media;
    }
}
