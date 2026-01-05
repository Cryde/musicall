<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\Builder\BandSpace\BandSpaceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceBuilder $bandSpaceBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships($uriVariables['id']);
        if (!$bandSpace) {
            throw new NotFoundHttpException('Band space not found');
        }

        if (!$this->bandSpaceMembershipRepository->isMember($bandSpace, $user)) {
            throw new AccessDeniedHttpException('You are not a member of this band space');
        }

        return $this->bandSpaceBuilder->buildItem($bandSpace);
    }
}
