<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class FinanceAmountRange extends Constraint
{
    public string $message = 'Le montant minimum doit être inférieur ou égal au montant maximum';
    public string $exclusiveMessage = 'Vous ne pouvez pas définir un montant exact et une fourchette en même temps';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
