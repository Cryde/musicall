<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Teacher;

use App\Entity\Teacher\TeacherProfileInstrument;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<TeacherProfileInstrument>
 */
final class TeacherProfileInstrumentFactory extends PersistentProxyObjectFactory
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
