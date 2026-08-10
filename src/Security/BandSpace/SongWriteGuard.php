<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\Song;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

readonly class SongWriteGuard
{
    public function assertWritable(Song $song): void
    {
        if ($song->archiveDatetime !== null) {
            throw new ConflictHttpException('Cette chanson est archivée, les modifications sont désactivées');
        }
    }
}
