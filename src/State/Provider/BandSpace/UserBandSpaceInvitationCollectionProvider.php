<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Invitation\UserBandSpaceInvitation;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Service\Builder\BandSpace\BandSpaceInvitationBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<UserBandSpaceInvitation>
 */
readonly class UserBandSpaceInvitationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private BandSpaceInvitationBuilder $bandSpaceInvitationBuilder,
        private Security $security,
    ) {
    }

    /** @return list<UserBandSpaceInvitation> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->bandSpaceInvitationBuilder->buildReceivedList(
            $this->bandSpaceInvitationRepository->findAcceptableBy($user),
        );
    }
}
