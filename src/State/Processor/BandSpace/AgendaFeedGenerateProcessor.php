<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaFeedGenerated;
use App\Entity\BandSpace\AgendaFeedToken;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaFeedTokenRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\ShareTokenService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mints, or rotates, the caller's iCal subscription URL for one band space.
 *
 * @implements ProcessorInterface<mixed, AgendaFeedGenerated>
 */
readonly class AgendaFeedGenerateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private AgendaFeedTokenRepository $feedTokenRepository,
        private ShareTokenService $tokenService,
        private BandSpaceActivityRecorder $activityRecorder,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgendaFeedGenerated
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMemberForWrite(
            (string) $uriVariables['bandSpaceId'],
            $user,
        );

        $tokenPair = $this->tokenService->generate();

        // One row per membership, so a second call rotates the hash in place rather than leaving two
        // live URLs behind. That is also what makes this the revoke-and-replace action the UI calls
        // « Régénérer »: the previous URL stops working the moment this returns.
        $feedToken = $this->feedTokenRepository->findOneByMembership($membership) ?? new AgendaFeedToken();
        $feedToken->membership = $membership;
        $feedToken->tokenHash = $tokenPair['hash'];
        // Every counter on the row describes a URL, and the URL has just changed, so they are reset
        // with it. Carried over, a link generated seconds ago would report itself as created last
        // month and already fetched forty times, which is the retired one's history.
        $feedToken->creationDatetime = new DateTimeImmutable();
        $feedToken->accessCount = 0;
        $feedToken->lastAccessDatetime = null;

        $this->entityManager->persist($feedToken);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Agenda,
            type: BandSpaceAgendaActivityType::FeedGenerated,
            actor: $user,
        );

        $this->entityManager->flush();

        $generated = new AgendaFeedGenerated();
        $generated->feedUrl = $this->urlGenerator->generate(
            'api_band_space_agenda_feed_download',
            ['token' => $tokenPair['token']],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $generated->creationDatetime = $feedToken->creationDatetime;

        return $generated;
    }
}
