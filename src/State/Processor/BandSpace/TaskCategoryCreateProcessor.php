<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCategoryCreate;
use App\ApiResource\BandSpace\Task\TaskCategoryResource;
use App\Entity\BandSpace\TaskCategory;
use App\Entity\User;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\ColorAssignmentService;
use App\Service\Builder\BandSpace\TaskCategoryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<TaskCategoryCreate, TaskCategoryResource>
 */
readonly class TaskCategoryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskCategoryRepository $taskCategoryRepository,
        private ColorAssignmentService $colorAssignmentService,
        private TaskCategoryBuilder $taskCategoryBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TaskCategoryCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskCategoryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $usedColors = $this->taskCategoryRepository->findColorsByBandSpace($bandSpace);

        $category = new TaskCategory();
        $category->bandSpace = $bandSpace;
        $category->name = $data->name;
        $category->color = $this->colorAssignmentService->assignColor($usedColors);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $this->taskCategoryBuilder->buildItem($category);
    }
}
