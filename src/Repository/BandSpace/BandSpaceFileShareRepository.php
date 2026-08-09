<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFileShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceFileShare>
 */
class BandSpaceFileShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceFileShare::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?BandSpaceFileShare
    {
        return $this->createQueryBuilder('s')
            ->addSelect('f')
            ->innerJoin('s.bandSpaceFile', 'f')
            ->where('s.tokenHash = :hash')
            ->setParameter('hash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return BandSpaceFileShare[]
     */
    public function findActiveByBandSpace(BandSpace $bandSpace, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('f', 'u')
            ->innerJoin('s.bandSpaceFile', 'f')
            ->leftJoin('s.createdBy', 'u')
            ->where('f.bandSpace = :bandSpace')
            ->andWhere('s.revocationDatetime IS NULL')
            ->andWhere('s.expiryDatetime IS NULL OR s.expiryDatetime > :now')
            ->andWhere('f.archiveDatetime IS NULL')
            ->setParameter('bandSpace', $bandSpace)
            ->setParameter('now', $now)
            ->orderBy('s.creationDatetime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every share of the given files that has not been revoked yet, expired ones included: an expiry
     * can be read off the row, a revocation is what makes the link dead for good.
     *
     * @param string[] $fileIds
     *
     * @return BandSpaceFileShare[]
     */
    public function findUnrevokedByFileIds(array $fileIds): array
    {
        if (count($fileIds) === 0) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.bandSpaceFile IN (:fileIds)')
            ->andWhere('s.revocationDatetime IS NULL')
            ->setParameter('fileIds', $fileIds)
            ->orderBy('s.creationDatetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndBandSpace(string $id, BandSpace $bandSpace): ?BandSpaceFileShare
    {
        return $this->createQueryBuilder('s')
            ->addSelect('f')
            ->innerJoin('s.bandSpaceFile', 'f')
            ->where('s.id = :id')
            ->andWhere('f.bandSpace = :bandSpace')
            ->setParameter('id', $id)
            ->setParameter('bandSpace', $bandSpace)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
