<?php

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Model\Search\Musician;
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
    public function findByCriteria(Musician $musician, ?User $currentUser, int $limit = 10)
    {
        $qb = $this->createQueryBuilder('musician_announce')
            ->select('musician_announce')
            ->where('musician_announce.instrument = :instrument')
            ->andWhere('musician_announce.type = :type')
            ->setParameter('type', $musician->getType())
            ->setParameter('instrument', $musician->getInstrument())
            ->setMaxResults($limit);

        if ($currentUser) {
            $qb->andWhere('musician_announce.author != :current_user')
                ->setParameter('current_user', $currentUser);
        }

        if ($styles = $musician->getStyles()) {
            $qb->leftJoin('musician_announce.styles', 'styles')
                ->andWhere('styles IN (:styles)')
                ->addSelect('styles')
                ->setParameter('styles', $styles);
        }

        if ($musician->getLatitude() && $musician->getLongitude()) {
            $qb->addSelect("
            ST_DISTANCE(ST_GeomFromText(:point), ST_POINT(musician_announce.longitude, musician_announce.latitude)) as distance
            ")
                ->setParameter('point', 'POINT(' . $musician->getLongitude() . ' ' . $musician->getLatitude() . ')')
                ->orderBy('distance', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}
