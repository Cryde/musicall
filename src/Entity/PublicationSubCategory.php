<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Repository\PublicationSubCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PublicationSubCategoryRepository::class)]
#[ApiResource(operations: [
    new Get(
        openapi: new Operation(tags: ['Publications']),
        normalizationContext: ['groups' => [PublicationSubCategory::ITEM]]
    ),
])]
class PublicationSubCategory
{
    final const int TYPE_PUBLICATION = 1;
    final const int TYPE_COURSE = 2;

    final const string TYPE_PUBLICATION_LABEL = 'publication';
    final const string TYPE_COURSE_LABEL = 'course';

    final const string LIST = 'PUBLICATION_CATEGORY_LIST';
    final const string ITEM = 'PUBLICATION_CATEGORY_ITEM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Groups([PublicationSubCategory::LIST, Publication::ITEM, Publication::LIST])]
    public ?int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Groups([PublicationSubCategory::LIST, Publication::ITEM, Publication::LIST])]
    public string $title;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Groups([PublicationSubCategory::LIST, Publication::ITEM, Publication::LIST])]
    public string $slug;

    /**
     * @var Collection<int, Publication>
     */
    #[ORM\OneToMany(mappedBy: "subCategory", targetEntity: Publication::class, orphanRemoval: true)]
    public Collection $publications;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups([PublicationSubCategory::LIST])]
    public ?int $position;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Groups([PublicationSubCategory::LIST])]
    public ?int $type;

    public function __construct()
    {
        $this->publications = new ArrayCollection();
    }

    #[Groups([Publication::LIST])]
    public function getTypeLabel(): string
    {
        return $this->type === self::TYPE_PUBLICATION ? self::TYPE_PUBLICATION_LABEL : self::TYPE_COURSE_LABEL;
    }

    #[Groups([Publication::LIST])]
    public function getIsCourse(): bool
    {
        return $this->type === self::TYPE_COURSE;
    }
}
