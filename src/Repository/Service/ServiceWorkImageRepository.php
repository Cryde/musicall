<?php

declare(strict_types=1);

namespace App\Repository\Service;

use App\Entity\Service\ServiceWorkImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceWorkImage>
 */
class ServiceWorkImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceWorkImage::class);
    }

    /** One image under its work and service, without loading the service's other works. */
    public function findOneInWork(string $serviceId, string $workId, string $id): ?ServiceWorkImage
    {
        /** @var ?ServiceWorkImage $image */
        $image = $this->createQueryBuilder('image')
            ->innerJoin('image.work', 'work')
            ->addSelect('work')
            ->where('image.id = :id')
            ->andWhere('work.id = :workId')
            ->andWhere('work.service = :serviceId')
            ->setParameter('id', $id)
            ->setParameter('workId', $workId)
            ->setParameter('serviceId', $serviceId)
            ->getQuery()
            ->getOneOrNullResult();

        return $image;
    }
}
