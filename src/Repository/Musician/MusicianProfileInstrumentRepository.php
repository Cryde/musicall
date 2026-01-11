<?php

declare(strict_types=1);

namespace App\Repository\Musician;

use App\Entity\Musician\MusicianProfileInstrument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MusicianProfileInstrument>
 */
class MusicianProfileInstrumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MusicianProfileInstrument::class);
    }
}
