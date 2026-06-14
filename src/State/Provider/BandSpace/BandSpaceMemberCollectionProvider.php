<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\BandSpaceMemberBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceMemberCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
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

        // Any member may read the active roster: it carries no sensitive data (username, role,
        // avatar, status), and member-accessible features - finance splits, task assignment -
        // need it. Admin-only mutations (role change / kick) stay on the separate item
        // provider + processors.
        [$bandSpace, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // Former members (left/kicked) are membership history: keep that admin-only, so a plain
        // member can only enumerate the active roster, not who was removed and when.
        $includeInactive = ($context['filters']['include_inactive'] ?? null) === 'true'
            && $membership->role === Role::Admin;
        $memberships = $this->bandSpaceMembershipRepository->findByBandSpace($bandSpace, $includeInactive);

        return $this->bandSpaceMemberBuilder->buildList($memberships);
    }
}
