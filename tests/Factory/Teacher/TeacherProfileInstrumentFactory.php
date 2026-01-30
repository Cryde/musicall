<?php

declare(strict_types=1);

namespace App\Tests\Factory\Teacher;

use App\Entity\Teacher\TeacherProfileInstrument;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TeacherProfileInstrument>
 */
final class TeacherProfileInstrumentFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'teacherProfile' => TeacherProfileFactory::new(),
            'instrument' => InstrumentFactory::new(),
        ];
    }

    public static function class(): string
    {
        return TeacherProfileInstrument::class;
    }
}
