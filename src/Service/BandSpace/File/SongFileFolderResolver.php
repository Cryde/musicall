<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\Song;
use App\Entity\BandSpace\SongFileFolder;
use App\Repository\BandSpace\SongFileFolderRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The song folder a request body names (#1451): one of this song's folders, or none for the song's root.
 */
readonly class SongFileFolderResolver
{
    public function __construct(private SongFileFolderRepository $folderRepository)
    {
    }

    public function resolve(?string $folderId, Song $song): ?SongFileFolder
    {
        if ($folderId === null || $folderId === '') {
            return null;
        }

        $folder = $this->folderRepository->findOneByIdAndSong($folderId, $song);
        if (!$folder instanceof SongFileFolder) {
            throw new BadRequestHttpException('band_space.song_file_folder.not_found_in_song');
        }

        return $folder;
    }
}
