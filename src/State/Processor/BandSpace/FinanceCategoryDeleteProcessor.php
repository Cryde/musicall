<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileSourceDetacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceCategoryResource, void>
 */
readonly class FinanceCategoryDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private BandSpaceFileSourceDetacher $fileSourceDetacher,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceCategoryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdminForWrite((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$category instanceof \App\Entity\BandSpace\FinanceCategory) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Finance,
            type: BandSpaceFinanceActivityType::CategoryDeleted,
            resourceId: $category->id,
            actor: $user,
            payload: ['name' => $category->name],
        );

        // `finance_entry.category_id` is ON DELETE CASCADE and the category declares no inverse
        // collection, so its entries go with it in the database without Doctrine ever loading them.
        // Their files have to be detached first, while the entries can still be named.
        $this->fileSourceDetacher->detachDeletedSources(
            $bandSpace,
            'finance',
            $this->financeEntryRepository->findLabelsByCategory($category),
            $user,
        );

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
