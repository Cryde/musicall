<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\Setlist\Song;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Setlist\Song\SongResource;
use App\Entity\User;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\SongBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class SongCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private SongBuilder $songBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return SongResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $includeArchived = isset($context['filters']['includeArchived'])
            && filter_var($context['filters']['includeArchived'], FILTER_VALIDATE_BOOLEAN);

        $songs = $this->songRepository->findByBandSpace($bandSpace, $includeArchived);

        return $this->songBuilder->buildFromList($songs);
    }
}
