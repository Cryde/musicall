<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RecurrenceEndDate extends Constraint
{
    public const int MAX_YEARS = 3;

    public string $messageBeforeStart = 'La date de fin doit être postérieure à la date de début';
    public string $messageMaxDuration = 'La durée maximale est de {{ limit }} ans';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
