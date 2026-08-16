<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceEntryDeleteProcessor;
use App\State\Processor\BandSpace\FinanceEntryUpdateProcessor;
use App\State\Provider\BandSpace\FinanceEntryCollectionProvider;
use App\State\Provider\BandSpace\FinanceEntryItemProvider;
use App\Validator\BandSpace\FinanceAmountRange;
use App\Validator\BandSpace\PersonalScopeWithoutSplits;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The PATCH is validated on this class, not on a dedicated input: API Platform merges the request into
 * the resource the provider returned, so the constraints below see the entry as it will be once the
 * write lands. That is the point. The old shape carried no constraint at all, and every rule the create
 * endpoint enforces was reachable in reverse through an edit: a negative amount, a minimum above its
 * maximum, an exact amount next to a fourchette, a blank libellé, and a date string that only ever
 * failed inside new DateTime().
 */
#[FinanceAmountRange]
#[PersonalScopeWithoutSplits]
#[ApiResource(
    shortName: 'FinanceEntry',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_get_collection',
            provider: FinanceEntryCollectionProvider::class,
            parameters: [
                'from' => new QueryParameter(key: 'from', constraints: [new Assert\Date()]),
                'to' => new QueryParameter(key: 'to', constraints: [new Assert\Date()]),
            ],
        ),
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_get_item',
            provider: FinanceEntryItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_patch',
            provider: FinanceEntryItemProvider::class,
            processor: FinanceEntryUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Finance']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_finance_entries_delete',
            provider: FinanceEntryItemProvider::class,
            processor: FinanceEntryDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class FinanceEntryResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    #[Assert\NotBlank(message: 'Veuillez spécifier une catégorie')]
    #[Assert\Uuid(message: 'Identifiant de catégorie invalide')]
    public string $categoryId;

    public string $categoryName;

    #[Assert\NotBlank(message: 'Veuillez spécifier un libellé')]
    #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
    public string $label;

    #[Assert\Choice(choices: ['expense', 'income'], message: 'Type invalide')]
    public string $type;

    #[Assert\Choice(choices: ['planned', 'committed', 'paid'], message: 'Statut invalide')]
    public string $status;

    #[Assert\PositiveOrZero(message: 'Le montant doit être positif ou zéro')]
    public ?int $amount = null;

    #[Assert\PositiveOrZero(message: 'Le montant minimum doit être positif ou zéro')]
    public ?int $amountMin = null;

    #[Assert\PositiveOrZero(message: 'Le montant maximum doit être positif ou zéro')]
    public ?int $amountMax = null;

    #[Assert\NotBlank(message: 'Veuillez spécifier une date')]
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    public string $date;

    #[Assert\Choice(choices: ['band', 'personal'], message: 'Périmètre invalide')]
    public string $scope;

    #[Assert\Uuid(message: 'Identifiant de membre invalide')]
    public ?string $memberId = null;

    public ?string $memberName = null;
    public bool $isFormerMember = false;
    public ?string $recurrenceId = null;
    public bool $splitWarning = false;
    public \DateTimeInterface $creationDatetime;
    public ?\DateTimeInterface $updateDatetime = null;
}
