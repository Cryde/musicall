<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use Symfony\Component\Validator\Constraint;

/**
 * Shape check on a reorder payload. Modelled on SetlistReorderPositions.
 *
 * Requires a contiguous 0..n-1 sequence, so a partial payload cannot silently renumber the
 * sections it left out. Membership of the rider is checked in the processor, which is the
 * only place that knows the rider.
 */
#[\Attribute]
class TechRiderSectionPositions extends Constraint
{
    public const string ERROR_CODE = 'music_all_6d2b8e14-9f37-4a5c-8e60-1c4d7b93af52';

    public string $emptyMessage = 'Les positions sont requises';
    public string $invalidItemMessage = 'Chaque position doit contenir un id (UUID) et une position (entier)';
    public string $notContiguousMessage = 'Les positions doivent former une séquence 0..n-1 sans trou ni doublon';
    public string $duplicateIdMessage = 'Chaque section ne peut apparaître qu\'une seule fois';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
