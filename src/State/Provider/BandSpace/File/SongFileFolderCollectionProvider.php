<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\SongFileFolderResource;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Repository\BandSpace\SongFileFolderRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\SongFileFolderBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * One song's folders, or every song's when the URI names no song.
 *
 * @implements ProviderInterface<SongFileFolderResource>
 */
readonly class SongFileFolderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private SongFileFolderRepository $folderRepository,
        private SongFileFolderBuilder $folderBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return SongFileFolderResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        if (!isset($uriVariables['songId'])) {
            return $this->folderBuilder->buildList($this->folderRepository->findByBandSpace($bandSpace));
        }

        $song = $this->songRepository->findOneByIdAndBandSpace((string) $uriVariables['songId'], $bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('band_space.song.not_found');
        }

        return $this->folderBuilder->buildList($this->folderRepository->findBySong($song));
    }
}
