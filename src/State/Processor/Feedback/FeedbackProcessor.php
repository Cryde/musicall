<?php declare(strict_types=1);

namespace App\State\Processor\Feedback;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Feedback\FeedbackResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Feedback\Feedback;
use App\Entity\User;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<FeedbackResource, FeedbackResource>
 */
readonly class FeedbackProcessor implements ProcessorInterface
{
    private const int USER_AGENT_MAX_LENGTH = 255;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private Security $security,
        #[Target('feedback_submit')]
        private RateLimiterFactoryInterface $feedbackSubmitLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FeedbackResource
    {
        $request = $this->requestStack->getCurrentRequest();
        $this->feedbackSubmitLimiter->create($request?->getClientIp() ?? 'unknown')->consume()->ensureAccepted();

        $user = $this->security->getUser();

        $feedback = new Feedback();
        $feedback->type = FeedbackType::from($data->type);
        $feedback->module = FeedbackModule::from($data->module);
        $feedback->message = $data->message;
        $feedback->email = $data->email;
        $feedback->pageUrl = $data->pageUrl;
        $feedback->userAgent = $this->readUserAgent($request?->headers->get('User-Agent'));
        $feedback->user = $user instanceof User ? $user : null;
        $feedback->bandSpace = $this->resolveBandSpace($data->bandSpaceId, $feedback->user);

        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return $data;
    }

    /**
     * Truncated rather than rejected: a browser we have never seen is not a reason to lose the
     * report, and the column is only ever read by a human trying to reproduce a bug.
     */
    private function readUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        return mb_substr($userAgent, 0, self::USER_AGENT_MAX_LENGTH);
    }

    /**
     * Dropped silently rather than refused when the sender is not an active member, so a stranger
     * cannot pin a report onto a space they have nothing to do with. Silence rather than a 403
     * because the id is filled in by the client from the current route: a mismatch means a stale tab
     * or a space just left, and losing the association is a better outcome than losing the report.
     */
    private function resolveBandSpace(?string $bandSpaceId, ?User $user): ?BandSpace
    {
        if ($bandSpaceId === null || !$user instanceof User) {
            return null;
        }

        $bandSpace = $this->bandSpaceRepository->find($bandSpaceId);
        if (!$bandSpace instanceof BandSpace) {
            return null;
        }

        $membership = $this->bandSpaceMembershipRepository->findMembership($bandSpace, $user);

        return $membership instanceof BandSpaceMembership ? $bandSpace : null;
    }
}
