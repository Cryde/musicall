<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Teacher;

use App\Entity\Teacher\TeacherProfileInstrument;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<TeacherProfileInstrument>
 */
final class TeacherProfileInstrumentFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [];
    }

    public static function class(): string
    {
        return TeacherProfileInstrument::class;
    }
}
