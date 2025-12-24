<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\BandSpaceRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\Entity(repositoryClass: BandSpaceRepository::class)]
#[ORM\Table(name: 'band_space')]
class BandSpace
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public $id {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }
    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $name;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    /**
     * @var Collection<int, BandSpaceMembership>
     */
    #[ORM\OneToMany(targetEntity: BandSpaceMembership::class, mappedBy: 'bandSpace', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $memberships;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
        $this->memberships = new ArrayCollection();
    }
}
