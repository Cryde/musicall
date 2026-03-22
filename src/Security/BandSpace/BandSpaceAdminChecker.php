<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class BandSpaceAdminChecker
{
    public function __construct(
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    /**
     * Loads the band space, verifies the user is an admin member.
     * Returns the [BandSpace, BandSpaceMembership] tuple.
     *
     * @return array{BandSpace, BandSpaceMembership}
     */
    public function checkAdmin(string $bandSpaceId, User $user): array
    {
        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships($bandSpaceId);
        if (!$bandSpace) {
            throw new NotFoundHttpException('Band Space introuvable');
        }

        $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $user);
        if (!$membership) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas membre de ce Band Space');
        }

        if ($membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Vous devez être administrateur pour effectuer cette action');
        }

        return [$bandSpace, $membership];
    }
}
