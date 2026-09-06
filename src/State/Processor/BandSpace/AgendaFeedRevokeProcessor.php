<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\BandSpace\AgendaFeedToken;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaFeedTokenRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class AgendaFeedRevokeProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private AgendaFeedTokenRepository $feedTokenRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMemberForWrite(
            (string) $uriVariables['bandSpaceId'],
            $user,
        );

        $feedToken = $this->feedTokenRepository->findOneByMembership($membership);
        if (!$feedToken instanceof AgendaFeedToken) {
            throw new NotFoundHttpException('Aucun flux à révoquer');
        }

        // Deleted rather than flagged. There is at most one row per membership and nobody reads a
        // dead one, so a revocation datetime would only buy the 410 that a file share needs for the
        // human on its landing page. A calendar client has no human to read it.
        $this->entityManager->remove($feedToken);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Agenda,
            type: BandSpaceAgendaActivityType::FeedRevoked,
            actor: $user,
        );

        $this->entityManager->flush();

        return null;
    }
}
