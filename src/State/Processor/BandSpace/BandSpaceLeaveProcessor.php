<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
readonly class BandSpaceLeaveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships((string) $uriVariables['bandSpaceId']);
        if (!$bandSpace) {
            throw new NotFoundHttpException('Band Space introuvable');
        }

        $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $user);
        if (!$membership) {
            throw new AccessDeniedHttpException('Vous n\'êtes pas membre de ce Band Space');
        }

        if ($membership->role === Role::Admin && $this->bandSpaceMembershipRepository->countAdmins($bandSpace) === 1) {
            throw new ConflictHttpException('Vous devez promouvoir un autre membre administrateur avant de quitter');
        }

        $membership->status = MembershipStatus::Left;
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
