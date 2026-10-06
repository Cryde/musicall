<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\Gallery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gallery>
 */
class GalleryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gallery::class);
    }

    /**
     * What the moderation queue lists, with each author and their profile, since every row is named
     * after its author (#1118).
     *
     * @return Gallery[]
     */
    public function findPendingWithAuthors(): array
    {
        return $this->createQueryBuilder('gallery')
            ->innerJoin('gallery.author', 'author')
            ->innerJoin('author.profile', 'author_profile')
            ->addSelect('author', 'author_profile')
            ->where('gallery.status = :status')
            ->setParameter('status', Gallery::STATUS_PENDING)
            ->orderBy('gallery.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
