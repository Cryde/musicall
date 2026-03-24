<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Finance;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\FinanceEntryCreateProcessor;
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
    #[Assert\NotBlank(message: 'Veuillez spécifier une catégorie')]
    #[Assert\Uuid(message: 'Identifiant de catégorie invalide')]
    public string $categoryId;

    #[Assert\NotBlank(message: 'Veuillez spécifier un libellé')]
    #[Assert\Length(max: 255, maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères')]
    public string $label;

    #[Assert\NotBlank(message: 'Veuillez spécifier un type')]
    #[Assert\Choice(choices: ['expense', 'income'], message: 'Type invalide')]
    public string $type;

    #[Assert\NotBlank(message: 'Veuillez spécifier un statut')]
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

    #[Assert\NotBlank(message: 'Veuillez spécifier un périmètre')]
    #[Assert\Choice(choices: ['band', 'personal'], message: 'Périmètre invalide')]
    public string $scope;

    #[Assert\Uuid(message: 'Identifiant de membre invalide')]
    public ?string $memberId = null;
}
