<?php declare(strict_types=1);

namespace App\Security\BandSpace;

use App\Entity\BandSpace\Setlist;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

readonly class SetlistWriteGuard
{
    public function assertWritable(Setlist $setlist): void
    {
        if ($setlist->archiveDatetime !== null) {
            throw new ConflictHttpException('Cette setlist est archivée, les modifications sont désactivées');
        }
    }
}
