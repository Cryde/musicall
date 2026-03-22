<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationResource;
use App\Entity\User;
use App\Enum\BandSpace\InvitationStatus;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceInvitationResource, void>
 */
readonly class BandSpaceInvitationDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceInvitationResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $invitation = $this->bandSpaceInvitationRepository->findOneByIdAndBandSpace(
            (string) $uriVariables['id'],
            $bandSpace
        );

        if (!$invitation || $invitation->status !== InvitationStatus::Pending) {
            throw new NotFoundHttpException('Invitation introuvable');
        }

        $invitation->status = InvitationStatus::Expired;
        $this->entityManager->flush();
    }
}
