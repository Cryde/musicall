<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BandSpaceFileVersion>
 */
class BandSpaceFileVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceFileVersion::class);
    }

    /**
     * @return BandSpaceFileVersion[]
     */
    public function findByFileNewestFirst(BandSpaceFile $file): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('u')
            ->leftJoin('v.createdBy', 'u')
            ->where('v.bandSpaceFile = :file')
            ->setParameter('file', $file)
            ->orderBy('v.versionNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByFileAndVersionNumber(BandSpaceFile $file, int $versionNumber): ?BandSpaceFileVersion
    {
        return $this->createQueryBuilder('v')
            ->where('v.bandSpaceFile = :file')
            ->andWhere('v.versionNumber = :versionNumber')
            ->setParameter('file', $file)
            ->setParameter('versionNumber', $versionNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findMaxVersionNumber(BandSpaceFile $file): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('MAX(v.versionNumber) AS max_version')
            ->where('v.bandSpaceFile = :file')
            ->setParameter('file', $file)
            ->getQuery()
            ->getSingleScalarResult();

        return $result === null ? 0 : (int) $result;
    }
}
