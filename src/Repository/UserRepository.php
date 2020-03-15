<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @param string $login
     *
     * @return User|null
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
}
