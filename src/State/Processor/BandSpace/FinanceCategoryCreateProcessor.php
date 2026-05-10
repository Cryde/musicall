<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceCategoryCreate;
use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\BandSpace\FinanceCategory;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\FinanceCategoryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceCategoryCreate, FinanceCategoryResource>
 */
readonly class FinanceCategoryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private FinanceCategoryBuilder $financeCategoryBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceCategoryCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceCategoryResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $parent = null;
        if ($data->parentId !== null) {
            $parent = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->parentId, $bandSpace);
            if (!$parent instanceof \App\Entity\BandSpace\FinanceCategory) {
                throw new NotFoundHttpException('Catégorie parente introuvable');
            }
        }

        $category = new FinanceCategory();
        $category->bandSpace = $bandSpace;
        $category->name = $data->name;
        $category->parent = $parent;
        $category->position = $this->financeCategoryRepository->getNextPosition($bandSpace, $parent);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Finance,
            type: BandSpaceFinanceActivityType::CategoryCreated,
            resourceId: $category->id,
            actor: $user,
            payload: ['name' => $category->name],
        );
        $this->entityManager->flush();

        return $this->financeCategoryBuilder->buildItem($category);
    }
}
