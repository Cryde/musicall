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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class TaskCategoryItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskCategoryRepository $taskCategoryRepository,
        private TaskCategoryBuilder $taskCategoryBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TaskCategoryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->taskCategoryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$category instanceof \App\Entity\BandSpace\TaskCategory) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        return $this->taskCategoryBuilder->buildItem($category);
    }
}
