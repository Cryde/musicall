<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\Builder\BandSpace\BandSpaceMemberBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        private Security $security,
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

        if (!$membership) {
            throw new NotFoundHttpException('Membre introuvable');
        }

        $newRole = Role::from($data->role);

        if ($newRole === Role::User
            && $membership->role === Role::Admin
            && $membership->user->id === $user->id
            && $this->bandSpaceMembershipRepository->countAdmins($bandSpace) === 1
        ) {
            throw new ConflictHttpException('Vous ne pouvez pas vous rétrograder car vous êtes le seul administrateur');
        }

        $membership->role = $newRole;
        $this->entityManager->flush();

        return $this->bandSpaceMemberBuilder->buildItem($membership);
    }
}
