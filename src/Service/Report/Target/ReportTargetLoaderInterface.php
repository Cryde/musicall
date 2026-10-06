<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Service\Report\ReportTarget;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Loads one kind of report target, but only if the reporter can see it: null means not found or not
 * theirs to see, and both answer the same 404.
 */
#[AutoconfigureTag('app.report_target_loader')]
interface ReportTargetLoaderInterface
{
    public function type(): ReportTargetType;

    public function load(string $id, User $reporter): ?ReportTarget;

    /**
     * The target as it stands now, whoever looks: what a moderator compares the snapshot with. Null
     * once it is gone, deleted or taken offline.
     */
    public function current(string $id): ?ReportTarget;
}
