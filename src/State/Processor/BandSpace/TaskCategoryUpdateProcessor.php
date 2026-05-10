<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskCategoryResource;
use App\Entity\User;
use App\Repository\BandSpace\TaskCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TaskCategoryBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TaskCategoryResource, TaskCategoryResource>
 */
readonly class TaskCategoryUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskCategoryRepository $taskCategoryRepository,
        private TaskCategoryBuilder $taskCategoryBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param TaskCategoryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TaskCategoryResource
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

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        if (array_key_exists('name', $requestPayload)) {
            $category->name = $data->name;
        }

        if (array_key_exists('color', $requestPayload)) {
            $category->color = $data->color;
        }

        $category->updateDatetime = new DateTime();

        $this->entityManager->flush();

        return $this->taskCategoryBuilder->buildItem($category);
    }
}
