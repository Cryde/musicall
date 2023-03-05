<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Publication|null find($id, $lockMode = null, $lockVersion = null)
 * @method Publication|null findOneBy(array $criteria, array $orderBy = null)
 * @method Publication[]    findAll()
 * @method Publication[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    /**
     *
     * @return int|mixed|string
     */
    public function findByTitleAndStatusAndType(string $title, int $status, int $type, int $limit = 10)
    {
        return $this->createQueryBuilder('publication')
            ->where('publication.status = :status')
            ->andWhere('publication.type = :type')
            ->andWhere('publication.title like :title')
            ->setParameter('status', $status)
            ->setParameter('type', $type)
            ->setParameter('title', '%' . $title . '%')
            ->orderBy('publication.publicationDatetime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Publication|null
     */
    public function findOneVideo(string $videoId)
    {
        return $this->findOneBy(['content' => $videoId, 'type' => Publication::TYPE_VIDEO]);
    }

    /**
     * @return float|int|mixed|string
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findOldCourseByOldId(int $oldId)
    {
        return $this->createQueryBuilder('publication')
            ->join('publication.subCategory', 'sub_category')
            ->where('publication.oldPublicationId = :id')
            ->andWhere('sub_category.type = :type')
            ->setParameter('id', $oldId)
            ->setParameter('type', PublicationSubCategory::TYPE_COURSE)
            ->getQuery()
            ->getSingleResult();
    }

    public function getBySearchTerm(string $term, int $limit = 10): array
    {
        return $this->createQueryBuilder('publication')
            ->where('publication.status = :status')
            ->andWhere('MATCH_AGAINST(publication.title, publication.shortDescription, publication.content) AGAINST(:term) > 0')
            ->andWhere('publication.cover IS NOT NULL')
            ->setParameter('term', $term)
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
