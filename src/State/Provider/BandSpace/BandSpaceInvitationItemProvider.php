<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\Builder\BandSpace\BandSpaceInvitationBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceInvitationItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private BandSpaceInvitationBuilder $bandSpaceInvitationBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $invitation = $this->bandSpaceInvitationRepository->findOneByIdAndBandSpace(
            (string) $uriVariables['id'],
            $bandSpace
        );

        if (!$invitation instanceof \App\Entity\BandSpace\BandSpaceInvitation) {
            throw new NotFoundHttpException('Invitation introuvable');
        }

        return $this->bandSpaceInvitationBuilder->buildItem($invitation);
    }
}
