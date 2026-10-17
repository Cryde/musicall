<?php

declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NoteMaxDepth extends Constraint
{
    public const int MAX_DEPTH = 3;

    public string $message = 'band_space.note.max_depth_reached';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
