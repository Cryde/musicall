<?php

namespace App\Repository\Wiki;

use App\Entity\Wiki\ArtistSocial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ArtistSocial|null find($id, $lockMode = null, $lockVersion = null)
 * @method ArtistSocial|null findOneBy(array $criteria, array $orderBy = null)
 * @method ArtistSocial[]    findAll()
 * @method ArtistSocial[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArtistSocialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArtistSocial::class);
    }
}
