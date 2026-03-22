<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationResource;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\Builder\BandSpace\BandSpaceInvitationBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceInvitationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private BandSpaceInvitationBuilder $bandSpaceInvitationBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return BandSpaceInvitationResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $invitations = $this->bandSpaceInvitationRepository->findPendingByBandSpace($bandSpace);

        return $this->bandSpaceInvitationBuilder->buildList($invitations);
    }
}
