<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Task\TaskCategoryResource;
use App\Entity\User;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskCategoryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class TaskCategoryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskCategoryRepository $taskCategoryRepository,
        private TaskCategoryBuilder $taskCategoryBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return TaskCategoryResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $categories = $this->taskCategoryRepository->findByBandSpace($bandSpace);

        return $this->taskCategoryBuilder->buildFromList($categories);
    }
}
