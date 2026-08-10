<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\SetlistBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class SetlistCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private SetlistBuilder $setlistBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return \App\ApiResource\BandSpace\Setlist\SetlistResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // The two lists never mix: archived=true is the trash, its absence is the live sidebar.
        $archivedOnly = $this->requestStack->getCurrentRequest()?->query->getBoolean('archived') ?? false;

        return $this->setlistBuilder->buildFromList(
            $this->setlistRepository->findByBandSpace($bandSpace, $archivedOnly),
        );
    }
}
