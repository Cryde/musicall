<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceEntrySplitDeleteProcessor;
use App\State\Provider\BandSpace\FinanceEntrySplitCollectionProvider;
use App\State\Provider\BandSpace\FinanceEntrySplitItemProvider;

#[ApiResource(
    shortName: 'FinanceEntrySplit',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{entryId}/splits',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'entryId' => new Link(fromClass: self::class, identifiers: ['entryId']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entry_splits_get_collection',
            provider: FinanceEntrySplitCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{entryId}/splits/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'entryId' => new Link(fromClass: self::class, identifiers: ['entryId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entry_splits_get_item',
            provider: FinanceEntrySplitItemProvider::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{entryId}/splits/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'entryId' => new Link(fromClass: self::class, identifiers: ['entryId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entry_splits_delete',
            provider: FinanceEntrySplitItemProvider::class,
            processor: FinanceEntrySplitDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class FinanceEntrySplitResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[ApiProperty(identifier: true)]
    public string $entryId;

    public ?string $memberId = null;
    public ?string $memberName = null;
    public bool $isFormerMember = false;
    public int $amount;
    public string $creationDatetime;
    public ?string $updateDatetime = null;
}
