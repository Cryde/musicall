<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\SongFileFolderResource;
use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\SongFileFolder;
use App\Entity\User;
use App\Repository\BandSpace\SongFileFolderRepository;
use App\Repository\BandSpace\SongRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\SongFileFolderBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<SongFileFolderResource>
 */
readonly class SongFileFolderItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private SongFileFolderRepository $folderRepository,
        private SongFileFolderBuilder $folderBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SongFileFolderResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $song = $this->songRepository->findOneByIdAndBandSpace((string) $uriVariables['songId'], $bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('band_space.song.not_found');
        }

        $folder = $this->folderRepository->findOneByIdAndSong((string) $uriVariables['id'], $song);
        if (!$folder instanceof SongFileFolder) {
            throw new NotFoundHttpException('band_space.song_file_folder.not_found');
        }

        return $this->folderBuilder->buildItem($folder);
    }
}
