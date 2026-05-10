<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class BandSpaceMemberChecker
{
    public function __construct(
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    /**
     * Loads the band space, verifies the user is a member.
     * Returns the [BandSpace, BandSpaceMembership] tuple.
     *
     * @return array{BandSpace, BandSpaceMembership}
     */
    public function checkMember(string $bandSpaceId, User $user): array
    {
        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships($bandSpaceId);
        if (!$bandSpace instanceof \App\Entity\BandSpace\BandSpace) {
            throw new NotFoundHttpException('Band Space introuvable');
        }

        $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $user);
        if (!$membership instanceof \App\Entity\BandSpace\BandSpaceMembership) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas membre de ce Band Space');
        }

        return [$bandSpace, $membership];
    }
}
