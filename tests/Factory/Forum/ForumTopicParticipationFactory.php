<?php

declare(strict_types=1);

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumTopicParticipation;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ForumTopicParticipation>
 */
final class ForumTopicParticipationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ForumTopicParticipation::class;
    }

    protected function defaults(): array
    {
        return [
            'readDatetime' => null,
            'removedDatetime' => null,
        ];
    }
}
