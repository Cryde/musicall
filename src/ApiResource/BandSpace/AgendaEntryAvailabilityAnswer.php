<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace;

use App\Enum\BandSpace\AvailabilityAnswer;
use Symfony\Component\Validator\Constraints as Assert;

class AgendaEntryAvailabilityAnswer
{
    /** Optional for a one-off entry, required for an occurrence of a recurring one. */
    #[Assert\Date(message: 'band_space.agenda.date_invalid')]
    public ?string $occurrenceDate = null;

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'band_space.availability.answer_required'),
        new Assert\Choice(callback: [self::class, 'answers'], message: 'band_space.availability.answer_invalid'),
    ])]
    public ?string $answer = null;

    /** @return list<string> */
    public static function answers(): array
    {
        return array_map(static fn (AvailabilityAnswer $answer): string => $answer->value, AvailabilityAnswer::cases());
    }
}
