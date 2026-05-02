<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class EntryNotPaid extends Constraint
{
    public string $message = 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
