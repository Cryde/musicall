<?php declare(strict_types=1);

namespace App\Repository\Attribute;

use App\Entity\Attribute\Style;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Style|null find($id, $lockMode = null, $lockVersion = null)
 * @method Style|null findOneBy(array $criteria, array $orderBy = null)
 * @method Style[]    findAll()
 * @method Style[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StyleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Style::class);
    }
}
