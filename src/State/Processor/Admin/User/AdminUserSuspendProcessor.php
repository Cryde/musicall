<?php declare(strict_types=1);

namespace App\State\Processor\Admin\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Admin\User\AdminUserSuspend;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Procedure\Moderation\AccountSuspensionProcedure;
use App\Service\Procedure\Moderation\ReportResolutionProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<AdminUserSuspend, null>
 */
readonly class AdminUserSuspendProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserRepository $userRepository,
        private AccountSuspensionProcedure $accountSuspension,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $moderator = $this->security->getUser();
        if (!$moderator instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $account = $this->userRepository->findOneById((string) $uriVariables['id']);
        if (!$account instanceof User || $account->isDeleted()) {
            throw new NotFoundHttpException('Utilisateur introuvable');
        }
        ReportResolutionProcedure::assertSuspendable($account, $moderator);

        if (!$account->isSuspended()) {
            $this->accountSuspension->suspend($account, trim($data->reason), $moderator);
        }

        return null;
    }
}
