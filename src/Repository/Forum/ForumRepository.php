<?php declare(strict_types=1);

namespace App\Repository\Forum;

use App\Entity\Forum\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    public function findBySlug(string $slug): ?Forum
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** The locale of the forum's category, alone: all its hreflang links need. */
    public function findLocaleBySlug(string $slug): ?string
    {
        $locale = $this->createQueryBuilder('forum')
            ->select('category.locale')
            ->innerJoin('forum.forumCategory', 'category')
            ->where('forum.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        return is_string($locale) ? $locale : null;
    }
}
