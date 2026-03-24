<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\User;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceRecurrenceResource, void>
 */
readonly class FinanceRecurrenceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceRecurrenceResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $recurrence = $this->financeRecurrenceRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$recurrence) {
            throw new NotFoundHttpException('Récurrence introuvable');
        }

        $this->entityManager->createQuery(
            'DELETE FROM App\Entity\BandSpace\FinanceEntry e
             WHERE e.recurrence = :recurrence
             AND e.status = :status'
        )
            ->setParameter('recurrence', $recurrence)
            ->setParameter('status', FinanceEntryStatus::Planned)
            ->execute();

        $this->entityManager->remove($recurrence);
        $this->entityManager->flush();
    }
}
