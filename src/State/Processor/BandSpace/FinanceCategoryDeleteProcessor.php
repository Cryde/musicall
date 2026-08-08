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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Deleting a category cascades its entries in the database, which used to make this the one route that
 * destroyed a Paid entry: FinanceEntryDeleteProcessor refuses one outright, and going through its
 * category took it out anyway, silently, along with the whole pole. So the delete now refuses while
 * anything it would take with it is accounting history.
 *
 * Sub-categories are refused for the same reason one step down. `finance_category.parent_id` is
 * `SET NULL`, so they were never deleted with their parent: they resurfaced as top-level poles, which
 * both contradicted the confirmation the interface showed and put their own Paid entries out of reach
 * of the check above. Emptying the subtree first is one click more and no surprises.
 *
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

        $childCount = $category->children->count();
        if ($childCount > 1) {
            throw new UnprocessableEntityHttpException(
                sprintf('Cette catégorie contient %d sous-catégories. Supprimez-les d\'abord.', $childCount)
            );
        }
        if ($childCount === 1) {
            throw new UnprocessableEntityHttpException('Cette catégorie contient une sous-catégorie. Supprimez-la d\'abord.');
        }

        $paidEntryCount = $this->financeEntryRepository->countPaidByCategory($category);
        if ($paidEntryCount > 1) {
            throw new UnprocessableEntityHttpException(
                sprintf('Cette catégorie contient %d entrées payées. Repassez leur statut à Engagé ou déplacez-les d\'abord.', $paidEntryCount)
            );
        }
        if ($paidEntryCount === 1) {
            throw new UnprocessableEntityHttpException('Cette catégorie contient une entrée payée. Repassez son statut à Engagé ou déplacez-la d\'abord.');
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
