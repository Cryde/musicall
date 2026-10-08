<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use App\Enum\BandSpace\AvailabilityAnswer;
use Symfony\Component\Validator\Constraints as Assert;

class AgendaEntryAvailabilityAnswer
{
    /** Optional for a one-off entry, required for an occurrence of a recurring one. */
    #[Assert\Date(message: 'Le format de la date est invalide (attendu : AAAA-MM-JJ)')]
    public ?string $occurrenceDate = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Veuillez indiquer votre disponibilité'),
        new Assert\Choice(callback: [self::class, 'answers'], message: 'La réponse doit être « yes » ou « no »'),
    ])]
    public ?string $answer = null;

    /** @return list<string> */
    public static function answers(): array
    {
        return array_map(static fn (AvailabilityAnswer $answer): string => $answer->value, AvailabilityAnswer::cases());
    }
}
