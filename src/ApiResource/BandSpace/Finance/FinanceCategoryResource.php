<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceCategoryDeleteProcessor;
use App\State\Processor\BandSpace\FinanceCategoryUpdateProcessor;
use App\State\Provider\BandSpace\FinanceCategoryCollectionProvider;
use App\State\Provider\BandSpace\FinanceCategoryItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'FinanceCategory',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/categories',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_categories_get_collection',
            provider: FinanceCategoryCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/categories/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_categories_get_item',
            provider: FinanceCategoryItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/categories/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_categories_patch',
            provider: FinanceCategoryItemProvider::class,
            processor: FinanceCategoryUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/categories/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_categories_delete',
            provider: FinanceCategoryItemProvider::class,
            processor: FinanceCategoryDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class FinanceCategoryResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un nom')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    public string $name;

    public ?string $parentId = null;

    #[Assert\PositiveOrZero(message: 'La position doit être positive ou zéro')]
    public int $position;

    public bool $hasChildren = false;
    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
