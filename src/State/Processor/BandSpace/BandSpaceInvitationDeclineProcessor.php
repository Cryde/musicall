<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\InvitationStatus;
use App\Event\BandSpaceInvitationRespondedEvent;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<mixed, void>
 */
readonly class BandSpaceInvitationDeclineProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $invitation = $this->bandSpaceInvitationRepository->findPendingByToken((string) $uriVariables['token']);
        if (!$invitation instanceof \App\Entity\BandSpace\BandSpaceInvitation) {
            throw new NotFoundHttpException('Invitation introuvable ou expirée');
        }

        if ($invitation->existingUser instanceof \App\Entity\User) {
            if ($invitation->existingUser->id !== $user->id) {
                throw new AccessDeniedHttpException('Cette invitation ne vous est pas destinée');
            }
        } elseif (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            throw new AccessDeniedHttpException('Cette invitation ne vous est pas destinée');
        }

        $invitation->status = InvitationStatus::Declined;

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $invitation->bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::InvitationDeclined,
            resourceId: $invitation->id,
            actor: $user,
            payload: ['email' => $invitation->email],
        );

        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract): tell the inviter.
        $this->eventDispatcher->dispatch(
            new BandSpaceInvitationRespondedEvent($invitation, $user, InvitationStatus::Declined),
        );
    }
}
