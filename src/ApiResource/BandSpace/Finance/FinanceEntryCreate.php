<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceEntryCreateProcessor;
use App\Validator\BandSpace\FinanceAmountLimit;
use App\Validator\BandSpace\FinanceAmountRange;
use Symfony\Component\Validator\Constraints as Assert;

#[FinanceAmountRange]
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/finance/entries',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: FinanceEntryResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Finance']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: FinanceEntryResource::class,
    name: 'api_band_space_finance_entries_post',
    processor: FinanceEntryCreateProcessor::class,
)]
class FinanceEntryCreate
{
    #[Assert\NotBlank(message: 'band_space.finance.category.required')]
    #[Assert\Uuid(message: 'common.category.invalid_identifier')]
    public string $categoryId;

    #[Assert\NotBlank(message: 'band_space.finance.label_required')]
    #[Assert\Length(max: 255, maxMessage: 'common.label.too_long')]
    public string $label;

    #[Assert\NotBlank(message: 'common.type.required')]
    #[Assert\Choice(choices: ['expense', 'income'], message: 'band_space.finance.type_invalid')]
    public string $type;

    #[Assert\NotBlank(message: 'band_space.finance.status_required')]
    #[Assert\Choice(choices: ['planned', 'committed', 'paid'], message: 'band_space.finance.status_invalid')]
    public string $status;

    #[Assert\PositiveOrZero(message: 'band_space.finance.amount.positive_or_zero')]
    #[Assert\LessThanOrEqual(value: FinanceAmountLimit::MAX_CENTS, message: FinanceAmountLimit::MESSAGE)]
    public ?int $amount = null;

    #[Assert\PositiveOrZero(message: 'band_space.finance.amount.min_positive_or_zero')]
    #[Assert\LessThanOrEqual(value: FinanceAmountLimit::MAX_CENTS, message: FinanceAmountLimit::MESSAGE)]
    public ?int $amountMin = null;

    #[Assert\PositiveOrZero(message: 'band_space.finance.amount.max_positive_or_zero')]
    #[Assert\LessThanOrEqual(value: FinanceAmountLimit::MAX_CENTS, message: FinanceAmountLimit::MESSAGE)]
    public ?int $amountMax = null;

    #[Assert\NotBlank(message: 'band_space.finance.date_required')]
    #[Assert\Date(message: 'band_space.finance.date_invalid')]
    public string $date;

    #[Assert\NotBlank(message: 'band_space.finance.scope_required')]
    #[Assert\Choice(choices: ['band', 'personal'], message: 'band_space.finance.scope_invalid')]
    public string $scope;

    #[Assert\Uuid(message: 'band_space.finance.member.invalid_identifier')]
    public ?string $memberId = null;
}
