<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Entity\User;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\RecurrenceEntryGenerator;
use App\Service\Builder\BandSpace\FinanceRecurrenceBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceRecurrenceResource, FinanceRecurrenceResource>
 */
readonly class FinanceRecurrenceUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private FinanceRecurrenceBuilder $financeRecurrenceBuilder,
        private RecurrenceEntryGenerator $recurrenceEntryGenerator,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param FinanceRecurrenceResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceRecurrenceResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace, $currentMembership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $recurrence = $this->financeRecurrenceRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$recurrence) {
            throw new NotFoundHttpException('Récurrence introuvable');
        }

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        if (array_key_exists('label', $requestPayload)) {
            $recurrence->label = $data->label;
        }

        if (array_key_exists('type', $requestPayload)) {
            $recurrence->type = FinanceEntryType::from($data->type);
        }

        if (array_key_exists('amount', $requestPayload)) {
            $recurrence->amount = $data->amount;
        }

        if (array_key_exists('scope', $requestPayload)) {
            $recurrence->scope = FinanceEntryScope::from($data->scope);
        }

        if (array_key_exists('interval', $requestPayload)) {
            $recurrence->interval = RecurrenceInterval::from($data->interval);
        }

        if (array_key_exists('is_active', $requestPayload)) {
            $recurrence->isActive = $data->isActive;
        }

        if (array_key_exists('end_date', $requestPayload)) {
            $oldEndDate = $recurrence->endDate;
            $newEndDate = new DateTime($data->endDate);

            $recurrence->endDate = $newEndDate;

            if ($newEndDate > $oldEndDate) {
                $fromDate = $this->nextIntervalDate($oldEndDate, $recurrence->interval);
                $member = $recurrence->scope === FinanceEntryScope::Personal ? $currentMembership : null;
                $entries = $this->recurrenceEntryGenerator->generateEntries($recurrence, $member, $fromDate);

                foreach ($entries as $entry) {
                    $this->entityManager->persist($entry);
                }
            } elseif ($newEndDate < $oldEndDate) {
                $this->entityManager->createQuery(
                    'DELETE FROM App\Entity\BandSpace\FinanceEntry e
                     WHERE e.recurrence = :recurrence
                     AND e.date > :afterDate
                     AND e.status = :status'
                )
                    ->setParameter('recurrence', $recurrence)
                    ->setParameter('afterDate', $newEndDate)
                    ->setParameter('status', FinanceEntryStatus::Planned)
                    ->execute();
            }
        }

        $recurrence->updateDatetime = new DateTime();

        $this->entityManager->flush();

        return $this->financeRecurrenceBuilder->buildItem($recurrence);
    }

    private function nextIntervalDate(\DateTimeInterface $date, RecurrenceInterval $interval): \DateTimeInterface
    {
        $next = DateTime::createFromInterface($date);

        return match ($interval) {
            RecurrenceInterval::Weekly => $next->modify('+7 days'),
            RecurrenceInterval::Monthly => $next->modify('+1 month'),
            RecurrenceInterval::Quarterly => $next->modify('+3 months'),
            RecurrenceInterval::Yearly => $next->modify('+12 months'),
        };
    }
}
