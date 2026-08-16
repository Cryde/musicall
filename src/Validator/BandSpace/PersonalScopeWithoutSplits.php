<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PersonalScopeWithoutSplits extends Constraint
{
    public string $message = 'Supprimez d\'abord la répartition de cette entrée pour la passer en personnelle.';
    public string $recurrenceMessage = 'Supprimez d\'abord la répartition des échéances de cette récurrence pour la passer en personnelle.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
