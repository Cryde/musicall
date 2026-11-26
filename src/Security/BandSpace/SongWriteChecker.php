<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\SongFileFolder;
use App\Entity\User;
use App\Repository\BandSpace\SongFileFolderRepository;
use App\Repository\BandSpace\SongRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A write on one song of a space: member of a space that is not pending deletion, a song of that
 * space, and a song that is not archived.
 */
readonly class SongWriteChecker
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
        private SongWriteGuard $songWriteGuard,
        private SongFileFolderRepository $folderRepository,
    ) {
    }

    /**
     * @return array{0: BandSpace, 1: Song}
     */
    public function checkSongForWrite(string $bandSpaceId, string $songId, User $user): array
    {
        [$bandSpace] = $this->memberChecker->checkMemberForWrite($bandSpaceId, $user);

        $song = $this->songRepository->findOneByIdAndBandSpace($songId, $bandSpace);
        if (!$song instanceof Song) {
            throw new NotFoundHttpException('band_space.song.not_found');
        }

        $this->songWriteGuard->assertWritable($song);

        return [$bandSpace, $song];
    }

    /**
     * A write on one of the song's folders (#1451).
     *
     * @return array{0: BandSpace, 1: Song, 2: SongFileFolder}
     */
    public function checkFolderForWrite(string $bandSpaceId, string $songId, string $folderId, User $user): array
    {
        [$bandSpace, $song] = $this->checkSongForWrite($bandSpaceId, $songId, $user);

        $folder = $this->folderRepository->findOneByIdAndSong($folderId, $song);
        if (!$folder instanceof SongFileFolder) {
            throw new NotFoundHttpException('band_space.song_file_folder.not_found');
        }

        return [$bandSpace, $song, $folder];
    }
}
