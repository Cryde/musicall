<?php declare(strict_types=1);

namespace App\Validator\BandSpace\Setlist;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class SetlistReorderPositions extends Constraint
{
    public const string ERROR_CODE = 'music_all_8a1f4c5d-7e2b-4a9c-b3d1-6f8e2c0a4b1d';

    public string $emptyMessage = 'Les positions sont requises';
    public string $invalidItemMessage = 'Chaque position doit contenir un id (UUID) et une position (entier)';
    public string $notContiguousMessage = 'Les positions doivent former une séquence 0..n-1 sans trou ni doublon';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
