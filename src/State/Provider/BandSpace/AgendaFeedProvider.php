<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\AgendaFeedResource;
use App\Entity\BandSpace\AgendaFeedToken;
use App\Entity\User;
use App\Repository\BandSpace\AgendaFeedTokenRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<AgendaFeedResource>
 */
readonly class AgendaFeedProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private AgendaFeedTokenRepository $feedTokenRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgendaFeedResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [, $membership] = $this->memberChecker->checkMember($bandSpaceId, $user);

        $resource = new AgendaFeedResource();
        $resource->bandSpaceId = $bandSpaceId;

        // The caller's own feed, looked up by their membership: there is no reading of anyone else's,
        // for an admin no more than for anyone. And no URL, because only the hash was ever stored.
        $feedToken = $this->feedTokenRepository->findOneByMembership($membership);
        if ($feedToken instanceof AgendaFeedToken) {
            $resource->isEnabled = true;
            $resource->creationDatetime = $feedToken->creationDatetime;
            $resource->lastAccessDatetime = $feedToken->lastAccessDatetime;
            $resource->accessCount = $feedToken->accessCount;
        }

        return $resource;
    }
}
