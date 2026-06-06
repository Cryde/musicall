<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Event\BandSpaceMemberRoleChangedEvent;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\BandSpaceMemberBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<BandSpaceMember, BandSpaceMember>
 */
readonly class BandSpaceMemberUpdateRoleProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceMemberBuilder $bandSpaceMemberBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param BandSpaceMember $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceMember
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

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $oldRole = $membership->role;

        if (array_key_exists('role', $requestPayload)) {
            $newRole = Role::from($data->role);

            if ($newRole === Role::User
                && $membership->role === Role::Admin
                && $membership->user->id === $user->id
                && $this->bandSpaceMembershipRepository->countAdmins($bandSpace) === 1
            ) {
                throw new ConflictHttpException('Vous ne pouvez pas vous rétrograder car vous êtes le seul administrateur');
            }

            $membership->role = $newRole;
        }

        if (array_key_exists('status', $requestPayload) && ($data->status === 'active' && $membership->status !== MembershipStatus::Active)) {
            $membership->status = MembershipStatus::Active;
            $membership->leftDatetime = null;
        }

        $roleChanged = $oldRole !== $membership->role;

        if ($roleChanged) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Settings,
                type: BandSpaceSettingsActivityType::MemberRoleChanged,
                resourceId: $membership->user->id,
                actor: $user,
                payload: [
                    'from' => $oldRole->value,
                    'to' => $membership->role->value,
                    'target_user_id' => $membership->user->id,
                    'target_username' => $membership->user->username,
                ],
            );
        }

        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract); only on an actual role change.
        if ($roleChanged) {
            $this->eventDispatcher->dispatch(new BandSpaceMemberRoleChangedEvent($membership, $oldRole, $user));
        }

        return $this->bandSpaceMemberBuilder->buildItem($membership);
    }
}
