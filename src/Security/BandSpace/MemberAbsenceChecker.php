<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\Role;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Who may record, edit or delete an absence: a member manages their own, an admin manages anyone's.
 *
 * Membership in the band space is a separate question, already answered by BandSpaceMemberChecker
 * before this is reached; this only compares the two memberships it is handed.
 */
readonly class MemberAbsenceChecker
{
    public function canManage(BandSpaceMembership $target, BandSpaceMembership $actor): bool
    {
        return $actor->role === Role::Admin || (string) $target->id === (string) $actor->id;
    }

    public function assertCanManage(BandSpaceMembership $target, BandSpaceMembership $actor): void
    {
        if (!$this->canManage($target, $actor)) {
            throw new AccessDeniedHttpException('Vous ne pouvez gérer que vos propres indisponibilités');
        }
    }
}
