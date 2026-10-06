<?php

declare(strict_types=1);

namespace App\Tests\Factory\Report;

use App\Entity\Report\Report;
use App\Enum\Report\ReportReason;
use App\Enum\Report\ReportTargetType;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Report>
 */
final class ReportFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'reporter' => UserFactory::new(),
            'targetType' => ReportTargetType::User,
            'targetId' => self::faker()->uuid(),
            'reason' => ReportReason::Spam,
            'snapshotText' => 'Achetez mes followers, prix imbattables',
            'snapshotContext' => [],
        ];
    }

    public static function class(): string
    {
        return Report::class;
    }
}
