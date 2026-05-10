<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntryResource;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\FinanceEntryBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<FinanceEntryResource, FinanceEntryResource>
 */
readonly class FinanceEntryUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private FinanceCategoryRepository $financeCategoryRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private FinanceEntrySplitRepository $financeEntrySplitRepository,
        private FinanceEntryBuilder $financeEntryBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param FinanceEntryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceEntryResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace, $currentMembership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$entry instanceof \App\Entity\BandSpace\FinanceEntry) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        if ($entry->scope === FinanceEntryScope::Personal && $entry->member?->user->id !== $user->id) {
            throw new AccessDeniedHttpException('Vous ne pouvez modifier que vos propres entrées personnelles');
        }

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $oldStatus = $entry->status;
        $oldLabel = $entry->label;
        $oldAmount = $entry->amount;
        $oldDate = $entry->date->format('Y-m-d');
        $oldCategoryId = (string) $entry->category->id;
        $oldCategoryName = $entry->category->name;

        if (array_key_exists('status', $requestPayload)) {
            $newStatus = FinanceEntryStatus::from($data->status);
            if ($newStatus !== $entry->status && !$entry->status->canTransitionTo($newStatus)) {
                throw new BadRequestHttpException(
                    sprintf('Transition de statut interdite : %s → %s', $entry->status->label(), $newStatus->label())
                );
            }
            $entry->status = $newStatus;
        }

        $protectedFields = ['amount', 'amount_min', 'amount_max', 'type', 'category_id', 'date', 'label', 'scope', 'member_id'];
        if ($entry->status === FinanceEntryStatus::Paid) {
            foreach ($protectedFields as $field) {
                if (array_key_exists($field, $requestPayload)) {
                    throw new UnprocessableEntityHttpException('Impossible de modifier une entrée payée. Repassez le statut à Engagé.');
                }
            }
        }

        if (array_key_exists('label', $requestPayload)) {
            $entry->label = $data->label;
        }

        if (array_key_exists('type', $requestPayload)) {
            $entry->type = FinanceEntryType::from($data->type);
        }

        if (array_key_exists('amount', $requestPayload)) {
            $entry->amount = $data->amount;
        }

        if (array_key_exists('amount_min', $requestPayload)) {
            $entry->amountMin = $data->amountMin;
        }

        if (array_key_exists('amount_max', $requestPayload)) {
            $entry->amountMax = $data->amountMax;
        }

        if (array_key_exists('date', $requestPayload)) {
            $entry->date = new DateTime($data->date);
        }

        if (array_key_exists('scope', $requestPayload)) {
            $entry->scope = FinanceEntryScope::from($data->scope);
        }

        if (array_key_exists('category_id', $requestPayload)) {
            $category = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->categoryId, $bandSpace);
            if (!$category instanceof \App\Entity\BandSpace\FinanceCategory) {
                throw new NotFoundHttpException('Catégorie introuvable');
            }
            $entry->category = $category;
        }

        if (array_key_exists('member_id', $requestPayload)) {
            if ($data->memberId !== null) {
                $member = $this->bandSpaceMembershipRepository->findOneByIdAndBandSpace($data->memberId, $bandSpace);
                if (!$member instanceof \App\Entity\BandSpace\BandSpaceMembership) {
                    throw new NotFoundHttpException('Membre introuvable');
                }
                $entry->member = $member;
            } else {
                $entry->member = null;
            }
        }

        if ($entry->scope === FinanceEntryScope::Personal && !$entry->member instanceof \App\Entity\BandSpace\BandSpaceMembership) {
            $entry->member = $currentMembership;
        }

        $entry->updateDatetime = new DateTime();

        $this->recordChanges(
            $entry,
            $user,
            $oldStatus,
            $oldLabel,
            $oldAmount,
            $oldDate,
            $oldCategoryId,
            $oldCategoryName,
        );

        $this->entityManager->flush();

        $splitWarning = false;
        if ($entry->amount !== null) {
            $splitSum = $this->financeEntrySplitRepository->getSumByEntry($entry);
            $splitWarning = $splitSum > 0 && $splitSum !== $entry->amount;
        }

        return $this->financeEntryBuilder->buildItem($entry, $splitWarning);
    }

    private function recordChanges(
        FinanceEntry $entry,
        User $user,
        FinanceEntryStatus $oldStatus,
        string $oldLabel,
        ?int $oldAmount,
        string $oldDate,
        string $oldCategoryId,
        string $oldCategoryName,
    ): void {
        if ($oldStatus !== $entry->status) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->category->bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::EntryStatusChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldStatus->value, 'to' => $entry->status->value],
            );
        }

        if ($oldLabel !== $entry->label) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->category->bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::EntryLabelChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldLabel, 'to' => $entry->label],
            );
        }

        if ($oldAmount !== $entry->amount) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->category->bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::EntryAmountChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldAmount, 'to' => $entry->amount],
            );
        }

        $newDate = $entry->date->format('Y-m-d');
        if ($oldDate !== $newDate) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->category->bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::EntryDateChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: ['from' => $oldDate, 'to' => $newDate],
            );
        }

        $newCategoryId = (string) $entry->category->id;
        if ($oldCategoryId !== $newCategoryId) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $entry->category->bandSpace,
                module: BandSpaceModule::Finance,
                type: BandSpaceFinanceActivityType::EntryCategoryChanged,
                resourceId: $entry->id,
                actor: $user,
                payload: [
                    'from_id' => $oldCategoryId,
                    'from_name' => $oldCategoryName,
                    'to_id' => $newCategoryId,
                    'to_name' => $entry->category->name,
                ],
            );
        }
    }
}
