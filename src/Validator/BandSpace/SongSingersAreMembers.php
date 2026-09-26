<?php

declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

/**
 * Every singer the lyrics name belongs to the band, now or before (#1055). A former member stays
 * valid so a song they sang keeps saving; someone who never was a member is refused.
 */
#[\Attribute]
class SongSingersAreMembers extends Constraint
{
    public string $message = 'Un chanteur attribué n\'est pas membre de ce Band Space';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
