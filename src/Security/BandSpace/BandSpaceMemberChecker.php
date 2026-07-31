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
        private BandSpaceWriteGuard $writeGuard,
    ) {
    }

    /**
     * Same as checkMember(), and additionally rejects a space pending deletion. Processors use this one,
     * providers keep checkMember() so reads and downloads stay open during the grace period.
     *
     * Membership is verified first on purpose: a non-member must get a 403 rather than learn from a 409
     * that the space is pending deletion.
     *
     * @return array{BandSpace, BandSpaceMembership}
     */
    public function checkMemberForWrite(string $bandSpaceId, User $user): array
    {
        $result = $this->checkMember($bandSpaceId, $user);
        $this->writeGuard->assertWritable($result[0]);

        return $result;
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
