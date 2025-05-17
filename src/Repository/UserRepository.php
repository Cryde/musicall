<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
}
