<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\Builder\BandSpace\BandSpaceMemberBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceMemberItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceMemberBuilder $bandSpaceMemberBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $membership = $this->bandSpaceMembershipRepository->findOneByIdAndBandSpace(
            (string) $uriVariables['id'],
            $bandSpace
        );

        if (!$membership instanceof \App\Entity\BandSpace\BandSpaceMembership) {
            throw new NotFoundHttpException('Membre introuvable');
        }

        return $this->bandSpaceMemberBuilder->buildItem($membership);
    }
}
