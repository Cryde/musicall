<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\UserRepository;
use App\Service\Report\ReportTarget;

readonly class UserTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::User;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        return $this->current($id);
    }

    public function current(string $id): ?ReportTarget
    {
        $user = $this->userRepository->findOneById($id);
        if (!$user instanceof User || $user->isDeleted()) {
            return null;
        }

        return new ReportTarget(
            $user,
            ReportTarget::text((string) $user->profile->displayName, (string) $user->profile->bio),
            ['username' => $user->username],
        );
    }
}
