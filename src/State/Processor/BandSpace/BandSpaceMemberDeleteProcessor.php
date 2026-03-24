<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceMember, void>
 */
readonly class BandSpaceMemberDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceMember $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $membership = $this->bandSpaceMembershipRepository->findOneByIdAndBandSpace(
            (string) $uriVariables['id'],
            $bandSpace
        );

        if (!$membership) {
            throw new NotFoundHttpException('Membre introuvable');
        }

        if ($membership->user->id === $user->id) {
            throw new ConflictHttpException('Vous ne pouvez pas vous exclure vous-même. Utilisez la fonction "Quitter"');
        }

        $membership->status = MembershipStatus::Kicked;
        $membership->leftDatetime = new DateTime();

        $this->deactivatePersonalRecurrences($membership);

        $this->entityManager->flush();
    }

    private function deactivatePersonalRecurrences(BandSpaceMembership $membership): void
    {
        $recurrences = $this->financeRecurrenceRepository->findActivePersonalByMember($membership);
        $now = new DateTime();

        foreach ($recurrences as $recurrence) {
            $recurrence->isActive = false;
            $recurrence->updateDatetime = new DateTime();

            // Delete future planned entries
            $this->entityManager->createQuery(
                'DELETE FROM App\Entity\BandSpace\FinanceEntry e
                 WHERE e.recurrence = :recurrence
                 AND e.date > :now
                 AND e.status = :status'
            )
            ->setParameter('recurrence', $recurrence)
            ->setParameter('now', $now)
            ->setParameter('status', FinanceEntryStatus::Planned)
            ->execute();
        }
    }
}
