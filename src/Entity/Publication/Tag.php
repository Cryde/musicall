<?php

declare(strict_types=1);

namespace App\Entity\Publication;

use App\Repository\Publication\TagRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_publication_tag_slug', columns: ['slug'])]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    public string $label;

    #[ORM\Column(type: Types::STRING, length: 120, unique: true)]
    public string $slug;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
