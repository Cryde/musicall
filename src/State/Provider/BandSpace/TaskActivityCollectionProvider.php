<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Task\TaskActivityResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskActivityBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class TaskActivityCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private BandSpaceActivityRepository $bandSpaceActivityRepository,
        private TaskActivityBuilder $taskActivityBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return TaskActivityResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace((string) $uriVariables['taskId'], $bandSpace);
        if (!$task instanceof \App\Entity\BandSpace\Task) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $activities = $this->bandSpaceActivityRepository->findForResource(
            $bandSpace,
            BandSpaceModule::Task,
            (string) $task->id,
        );

        return $this->taskActivityBuilder->buildFromList($task, $activities);
    }
}
