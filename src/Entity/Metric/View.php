<?php

declare(strict_types=1);

namespace App\Entity\Metric;

use DateTime;
use DateTimeInterface;
use App\Entity\User;
use App\Repository\Metric\ViewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ViewRepository::class)]
#[ORM\Index(name: 'idx_view_entity', columns: ['entity_type', 'entity_id'])]
class View
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public int $id;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $identifier;

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $user = null;

    #[ORM\ManyToOne(targetEntity: ViewCache::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ViewCache $viewCache;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    public ?string $entityType = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    public ?string $entityId = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
