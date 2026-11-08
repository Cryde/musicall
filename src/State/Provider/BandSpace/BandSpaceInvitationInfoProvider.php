<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationInfo;
use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceInvitationInfoProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BandSpaceInvitationInfo
    {
        $token = (string) $uriVariables['token'];
        $this->refuseWhenTheViewerIsAlreadyMember($token);

        $invitation = $this->bandSpaceInvitationRepository->findPendingByToken($token);
        if (!$invitation instanceof BandSpaceInvitation) {
            throw new NotFoundHttpException('band_space.invitation.not_found_or_expired');
        }

        $dto = new BandSpaceInvitationInfo();
        $dto->token = $invitation->token;
        $dto->email = $invitation->email;
        $dto->bandSpaceName = $invitation->bandSpace->name;

        return $dto;
    }

    /**
     * An invitation accepted at sign-up still brings its member here through the return path, where
     * "expired" would send them asking for a new one. Whatever its status, a member is told they are in;
     * a visitor or a non-member sees exactly what they saw before.
     */
    private function refuseWhenTheViewerIsAlreadyMember(string $token): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $invitation = $this->bandSpaceInvitationRepository->findOneBy(['token' => $token]);
        if ($invitation instanceof BandSpaceInvitation && $this->bandSpaceMembershipRepository->isMember($invitation->bandSpace, $user)) {
            throw new ConflictHttpException('band_space.invitation.already_member');
        }
    }
}
