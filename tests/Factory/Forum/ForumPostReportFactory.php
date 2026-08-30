<?php

declare(strict_types=1);

namespace App\Tests\Factory\Forum;

use App\Entity\Forum\ForumPostReport;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ForumPostReport>
 */
final class ForumPostReportFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => new \DateTime(),
            'post' => ForumPostFactory::new(),
            'reason' => self::faker()->text(200),
            'reporter' => UserFactory::new(),
            'resolvedBy' => null,
            'resolvedDatetime' => null,
        ];
    }

    public static function class(): string
    {
        return ForumPostReport::class;
    }
}
