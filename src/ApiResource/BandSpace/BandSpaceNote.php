<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\BandSpaceNoteDeleteProcessor;
use App\State\Processor\BandSpace\BandSpaceNoteUpdateProcessor;
use App\State\Provider\BandSpace\BandSpaceNoteCollectionProvider;
use App\State\Provider\BandSpace\BandSpaceNoteItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/notes',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Note']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_notes_get_collection',
            provider: BandSpaceNoteCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/notes/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Note']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_notes_get_item',
            provider: BandSpaceNoteItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/notes/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Note']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_notes_patch',
            provider: BandSpaceNoteItemProvider::class,
            processor: BandSpaceNoteUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/notes/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Note']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_notes_delete',
            provider: BandSpaceNoteItemProvider::class,
            processor: BandSpaceNoteDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class BandSpaceNote
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un titre')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $title;

    #[Assert\Length(max: 30, maxMessage: 'L\'emoji ne peut pas dépasser {{ limit }} caractères')]
    public ?string $emoji = null;
    public ?string $parentId = null;

    #[Assert\PositiveOrZero(message: 'La position doit être positive ou zéro')]
    public int $position;
    /** @var array<string, mixed>|null */
    public ?array $content = null;
    public bool $hasChildren = false;
    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
