<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByTokenAndLimitDatetime(
        string $token,
        \DateTime $limitDatetime = new \DateTime('15 minutes ago')
    ): ?User
    {
        return $this->createQueryBuilder('user')
            ->where('user.token = :token')
            ->andWhere('user.resetRequestDatetime > :limit_datetime')
            ->setParameter('token', $token)
            ->setParameter('limit_datetime', $limitDatetime)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Will be deprecated in the future, the correct method is "loadUserByIdentifier"
     * @throws NonUniqueResultException
     */
    public function loadUserByUsername(string $username): ?User
    {
        return $this->findOneByEmailOrLogin($username);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneByEmailOrLogin($identifier);
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findOneByEmailOrLogin(string $login): ?User
    {
        return $this->createQueryBuilder('user')
            ->where('user.email = :login')
            ->orWhere('user.username = :login')
            ->setParameter('login', $login)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     *
     * @return int|mixed|string
     */
    public function searchByUserName(string $username, int $limit = 15): mixed
    {
        return $this->createQueryBuilder('user')
            ->where('user.username LIKE :search')
            ->setParameter('search', $username . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findInactiveUsersSince(\DateTimeImmutable $cutoffDate, int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('user')
            ->where('user.confirmationDatetime IS NOT NULL')
            ->andWhere('user.lastLoginDatetime IS NOT NULL')
            ->andWhere('user.lastLoginDatetime < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('user.lastLoginDatetime', 'ASC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find users who registered but never confirmed their email.
     *
     * @return User[]
     */
    public function findUsersWithUnconfirmedEmail(\DateTimeImmutable $registeredBefore, int $limit = 0): array
    {
        $qb = $this->createQueryBuilder('user')
            ->where('user.confirmationDatetime IS NULL')
            ->andWhere('user.token IS NOT NULL')
            ->andWhere('user.creationDatetime < :registeredBefore')
            ->setParameter('registeredBefore', $registeredBefore)
            ->orderBy('user.creationDatetime', 'ASC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
