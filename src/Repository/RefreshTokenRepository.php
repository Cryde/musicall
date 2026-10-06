<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Not the entity's repositoryClass, which stays the bundle's own: this only adds the revocation the
 * application needs.
 *
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * Ends every session of an account. DQL rather than find and remove: the rows are only loaded
     * to be thrown away, and a refresh token has no lifecycle listener. Keyed by username, which is
     * how the bundle stores them.
     */
    public function deleteForUsername(string $username): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\RefreshToken t WHERE t.username = :username')
            ->setParameter('username', $username)
            ->execute();
    }
}
