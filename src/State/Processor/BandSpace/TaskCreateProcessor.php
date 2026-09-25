<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCreate;
use App\ApiResource\BandSpace\Task\TaskResource;
use App\Entity\User;
use App\Procedure\BandSpace\TaskCreateProcedure;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<TaskCreate, TaskResource>
 */
readonly class TaskCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskCreateProcedure $taskCreateProcedure,
        private TaskBuilder $taskBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TaskCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        // A task created from the board has no chat message behind it, so nothing to link back to.
        return $this->taskBuilder->buildItem($this->taskCreateProcedure->create($data, $bandSpace, $user));
    }
}
