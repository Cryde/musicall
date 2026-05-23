<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCategoryResource;
use App\Entity\User;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskCategoryResource, void>
 */
readonly class TaskCategoryDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private TaskCategoryRepository $taskCategoryRepository,
        private Security $security,
    ) {
    }

    /**
     * @param TaskCategoryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->taskCategoryRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$category instanceof \App\Entity\BandSpace\TaskCategory) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
