<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceRecurrenceCreateProcessor;
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
    #[Assert\NotBlank(message: 'Veuillez spécifier une catégorie')]
    #[Assert\Uuid(message: 'Identifiant de catégorie invalide')]
    public string $categoryId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un libellé')]
    #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
    public string $label;

    #[Assert\NotBlank(message: 'Veuillez spécifier un type')]
    #[Assert\Choice(choices: ['expense', 'income'], message: 'Type invalide')]
    public string $type;

    #[Assert\NotBlank(message: 'Veuillez spécifier un montant')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    public int $amount;

    #[Assert\NotBlank(message: 'Veuillez spécifier un périmètre')]
    #[Assert\Choice(choices: ['band', 'personal'], message: 'Périmètre invalide')]
    public string $scope;

    #[Assert\NotBlank(message: 'Veuillez spécifier un intervalle')]
    #[Assert\Choice(choices: ['weekly', 'monthly', 'quarterly', 'yearly'], message: 'Intervalle de récurrence invalide')]
    public string $interval;

    #[Assert\NotBlank(message: 'Veuillez spécifier une date de début')]
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    public string $startDate;

    #[Assert\NotBlank(message: 'Veuillez spécifier une date de fin')]
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    public string $endDate;
}
