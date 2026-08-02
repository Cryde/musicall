<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * Which half of the patch list a row belongs to: what the band sends to the desk, and what it
 * needs back.
 *
 * One item holds both, because they are two tables on one page and a sound engineer reads them
 * together. Splitting them into two items would let a rider be composed with the inputs on
 * page 2 and the outputs on page 6.
 */
enum TechRiderPatchDirection: string
{
    case Input = 'input';
    case Output = 'output';

    public function label(): string
    {
        return match ($this) {
            self::Input => 'Entrées',
            self::Output => 'Retours',
        };
    }
}
