<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Publication|null find($id, $lockMode = null, $lockVersion = null)
 * @method Publication|null findOneBy(array $criteria, array $orderBy = null)
 * @method Publication[]    findAll()
 * @method Publication[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    /**
     * @param $videoId
     *
     * @return Publication|null
     */
    public function findOneVideo(string $videoId)
    {
        return $this->findOneBy(['content' => $videoId, 'category' => Publication::CATEGORY_PUBLICATION, 'type' => Publication::TYPE_VIDEO]);
    }

    /**
     * @param int $offset
     * @param int $limit
     *
     * @return Publication[]
     */
    public function findOnlinePublications(int $offset, int $limit)
    {
        return $this->findBy(
            ['status' => Publication::STATUS_ONLINE],
            ['publicationDatetime' => 'DESC'],
            $limit,
            $offset
        );
    }

    /**
     * @param PublicationSubCategory $publicationSubCategory
     * @param int                    $offset
     * @param int                    $limit
     *
     * @return Publication[]
     */
    public function findOnlinePublicationsByCategory(
        PublicationSubCategory $publicationSubCategory,
        int $offset,
        int $limit
    ) {
        return $this->findBy(
            ['status' => Publication::STATUS_ONLINE, 'subCategory' => $publicationSubCategory],
            ['publicationDatetime' => 'DESC'],
            $limit,
            $offset
        );
    }

    /**
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countOnlinePublications()
    {
        return $this->createQueryBuilder('publication')
            ->select('COUNT(publication.id) as total')
            ->where('publication.status = :status')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param PublicationSubCategory $publicationSubCategory
     *
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countOnlinePublicationsByCategory(PublicationSubCategory $publicationSubCategory)
    {
        return $this->createQueryBuilder('publication')
            ->select('COUNT(publication.id) as total')
            ->where('publication.status = :status')
            ->andWhere('publication.subCategory = :subcat')
            ->setParameter('status', Publication::STATUS_ONLINE)
            ->setParameter('subcat', $publicationSubCategory)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
