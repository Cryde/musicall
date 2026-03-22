<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\Builder\BandSpace\BandSpaceMemberBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceMemberCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceMemberBuilder $bandSpaceMemberBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return BandSpaceMember[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $memberships = $this->bandSpaceMembershipRepository->findByBandSpace($bandSpace);

        return $this->bandSpaceMemberBuilder->buildList($memberships);
    }
}
