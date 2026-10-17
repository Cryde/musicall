<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceRecurrenceCreateProcessor;
use App\Validator\BandSpace\FinanceAmountLimit;
use App\Validator\BandSpace\RecurrenceEndDate;
use App\Validator\BandSpace\RecurrenceNoOverlap;
use Symfony\Component\Validator\Constraints as Assert;

#[RecurrenceEndDate]
#[RecurrenceNoOverlap]
#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/finance/recurrences',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: FinanceRecurrenceResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Finance']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: FinanceRecurrenceResource::class,
    name: 'api_band_space_finance_recurrences_post',
    processor: FinanceRecurrenceCreateProcessor::class,
)]
class FinanceRecurrenceCreate
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

    #[Assert\NotBlank(message: 'band_space.finance.amount.required')]
    #[Assert\Positive(message: 'band_space.finance.amount.positive')]
    #[Assert\LessThanOrEqual(value: FinanceAmountLimit::MAX_CENTS, message: FinanceAmountLimit::MESSAGE)]
    public int $amount;

    #[Assert\NotBlank(message: 'band_space.finance.scope_required')]
    #[Assert\Choice(choices: ['band', 'personal'], message: 'band_space.finance.scope_invalid')]
    public string $scope;

    #[Assert\NotBlank(message: 'band_space.finance.recurrence.interval_required')]
    #[Assert\Choice(choices: ['weekly', 'monthly', 'quarterly', 'yearly'], message: 'band_space.finance.recurrence.interval_invalid')]
    public string $interval;

    #[Assert\NotBlank(message: 'band_space.finance.recurrence.start_date_required')]
    #[Assert\Date(message: 'band_space.finance.date_invalid')]
    public string $startDate;

    #[Assert\NotBlank(message: 'band_space.finance.recurrence.end_date_required')]
    #[Assert\Date(message: 'band_space.finance.date_invalid')]
    public string $endDate;
}
