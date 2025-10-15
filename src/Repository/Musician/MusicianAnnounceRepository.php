<?php

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Model\Search\MusicianSearch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MusicianAnnounce|null find($id, $lockMode = null, $lockVersion = null)
 * @method MusicianAnnounce|null findOneBy(array $criteria, array $orderBy = null)
 * @method MusicianAnnounce[]    findAll()
 * @method MusicianAnnounce[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MusicianAnnounceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianAnnounce::class);
    }

    /**
     * @return int|mixed|string
     */
    public function findByCriteria(MusicianSearch $musician, ?User $currentUser, int $limit = 10): mixed
    {
        $qb = $this->createQueryBuilder('musician_announce')
            ->select('musician_announce')
            ->addSelect('instrument')
            ->addSelect('author')
            ->join('musician_announce.instrument', 'instrument')
            ->join('musician_announce.author', 'author')
            ->where('musician_announce.instrument = :instrument')
            ->andWhere('musician_announce.type = :type')
            ->orderBy('musician_announce.creationDatetime', 'DESC')
            ->setParameter('type', $musician->type)
            ->setParameter('instrument', $musician->instrument)
            ->setMaxResults($limit);

        if ($currentUser) {
            $qb->andWhere('musician_announce.author != :current_user')
                ->setParameter('current_user', $currentUser);
        }

        if ($styles = $musician->styles) {
            $qb->leftJoin('musician_announce.styles', 'styles')
                ->andWhere('styles IN (:styles)')
                ->setParameter('styles', $styles);
        }

        if ($musician->latitude && $musician->longitude) {
            $qb->addSelect("
            ST_Distance_Sphere(ST_GeomFromText(:point), ST_POINT(musician_announce.longitude, musician_announce.latitude)) as distance
            ")
                ->setParameter('point', 'POINT(' . $musician->longitude . ' ' . $musician->latitude . ')')
                ->orderBy('distance', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}
