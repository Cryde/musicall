<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceEntrySplitCreateProcessor;
use App\Validator\BandSpace\SplitNotPersonal;
use Symfony\Component\Validator\Constraints as Assert;

#[SplitNotPersonal]
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries/{entryId}/splits',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: FinanceEntrySplitResource::class, identifiers: ['bandSpaceId']),
        'entryId' => new Link(fromClass: FinanceEntrySplitResource::class, identifiers: ['entryId']),
    ],
    openapi: new Operation(tags: ['Band Space Finance']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: FinanceEntrySplitResource::class,
    name: 'api_band_space_finance_entry_splits_post',
    processor: FinanceEntrySplitCreateProcessor::class,
)]
class FinanceEntrySplitCreate
{
    #[Assert\NotBlank(message: 'Veuillez spécifier un membre')]
    #[Assert\Uuid(message: 'Identifiant de membre invalide')]
    public string $memberId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un montant')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    public int $amount;
}
