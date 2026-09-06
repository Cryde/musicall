<?php declare(strict_types=1);

namespace App\State\Processor\Admin\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Admin\User\AdminUserRoleUpdate;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AdminUserRoleUpdate, null>
 */
readonly class AdminUserRoleUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->userRepository->findOneById((string) $uriVariables['id']);
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Utilisateur introuvable');
        }

        $currentUser = $this->security->getUser();
        // Nobody edits their own roles. It stops an admin dropping their own ROLE_ADMIN and locking
        // themselves out of a back office only another admin could let them back into, and it takes
        // the simplest self escalation path off the table.
        if ($currentUser instanceof User && $currentUser->id === $user->id) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas modifier vos propres rôles');
        }

        // A role the account holds that is not grantable here is kept rather than silently dropped:
        // this endpoint owns the grantable set, not the whole column.
        $preserved = array_diff($user->roles, AdminUserRoleUpdate::GRANTABLE_ROLES);
        $user->roles = array_values(array_unique([...$preserved, ...$data->roles]));

        $this->entityManager->flush();

        return null;
    }
}
