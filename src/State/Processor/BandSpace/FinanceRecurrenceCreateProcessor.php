<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceCreate;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\RecurrenceEntryGenerator;
use App\Service\Builder\BandSpace\FinanceRecurrenceBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceRecurrenceCreate, FinanceRecurrenceResource>
 */
readonly class FinanceRecurrenceCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private FinanceRecurrenceBuilder $financeRecurrenceBuilder,
        private RecurrenceEntryGenerator $recurrenceEntryGenerator,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceRecurrenceCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceRecurrenceResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace, $currentMembership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->categoryId, $bandSpace);
        if (!$category instanceof \App\Entity\BandSpace\FinanceCategory) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        $recurrence = new FinanceRecurrence();
        $recurrence->category = $category;
        $recurrence->label = $data->label;
        $recurrence->type = FinanceEntryType::from($data->type);
        $recurrence->amount = $data->amount;
        $recurrence->scope = FinanceEntryScope::from($data->scope);
        $recurrence->interval = RecurrenceInterval::from($data->interval);
        $recurrence->startDate = new \DateTime($data->startDate);
        $recurrence->endDate = new \DateTime($data->endDate);

        $this->entityManager->persist($recurrence);

        $member = $recurrence->scope === FinanceEntryScope::Personal ? $currentMembership : null;
        $entries = $this->recurrenceEntryGenerator->generateEntries($recurrence, $member);

        foreach ($entries as $entry) {
            $recurrence->entries->add($entry);
            $this->entityManager->persist($entry);
        }

        $this->entityManager->flush();

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Finance,
            type: BandSpaceFinanceActivityType::RecurrenceCreated,
            resourceId: $recurrence->id,
            actor: $user,
            payload: [
                'label' => $recurrence->label,
                'amount' => $recurrence->amount,
                'interval' => $recurrence->interval->value,
                'generated_entries' => count($entries),
            ],
        );
        $this->entityManager->flush();

        return $this->financeRecurrenceBuilder->buildItem($recurrence);
    }
}
