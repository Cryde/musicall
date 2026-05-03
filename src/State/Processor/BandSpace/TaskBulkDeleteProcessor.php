<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskBulkDelete;
use App\Entity\User;
use App\Procedure\BandSpace\TaskBulkDeleteProcedure;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<TaskBulkDelete, void>
 */
readonly class TaskBulkDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskBulkDeleteProcedure $taskBulkDeleteProcedure,
        private Security $security,
    ) {
    }

    /**
     * @param TaskBulkDelete $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $this->taskBulkDeleteProcedure->delete($bandSpace, $membership, $data->taskIds, $user);
    }
}
