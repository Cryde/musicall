<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist\Lyrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Setlist\Lyrics\LyricsListItem;
use App\Entity\User;
use App\Repository\BandSpace\LyricsRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\LyricsBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class LyricsCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private LyricsRepository $lyricsRepository,
        private LyricsBuilder $lyricsBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return LyricsListItem[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // archived=true is the Corbeille, as for songs and setlists.
        $archivedOnly = $this->requestStack->getCurrentRequest()?->query->getBoolean('archived') ?? false;

        return $this->lyricsBuilder->buildFromList(
            $this->lyricsRepository->findByBandSpace($bandSpace, $archivedOnly),
        );
    }
}
