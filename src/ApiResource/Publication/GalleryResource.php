<?php

declare(strict_types=1);

namespace App\ApiResource\Publication;

use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Gallery;
use App\Entity\User;
use App\State\Provider\Publication\GalleryCollectionProvider;
use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Gallery',
    operations: [
        new GetCollection(
            uriTemplate: '/galleries',
            openapi: new Operation(tags: ['Publications']),
            paginationEnabled: false,
            normalizationContext: ['groups' => [GalleryResource::LIST], 'skip_null_values' => false],
            name: 'api_gallery_get_collection',
            provider: GalleryCollectionProvider::class,
            stateOptions: new Options(entityClass: Gallery::class),
            parameters: [
                'order[publication_datetime]' => new QueryParameter(
                    filter: new SortFilter(),
                    property: 'publicationDatetime',
                    castToArray: false,
                ),
            ],
        ),
    ],
)]
class GalleryResource
{
    public const string LIST = 'gallery:list';

    #[ApiProperty(identifier: true)]
    #[Groups([GalleryResource::LIST])]
    public int $id;

    #[Groups([GalleryResource::LIST])]
    public string $title;

    #[Groups([GalleryResource::LIST])]
    public ?DateTimeInterface $publicationDatetime = null;

    #[Groups([GalleryResource::LIST])]
    public User $author;

    #[Groups([GalleryResource::LIST])]
    public ?string $coverImage = null;

    #[Groups([GalleryResource::LIST])]
    public ?string $slug = null;

    #[Groups([GalleryResource::LIST])]
    public int $imageCount = 0;
}
