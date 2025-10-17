<?php declare(strict_types=1);

namespace App\Service\Builder;

use App\Entity\Image\PublicationCover;

class PublicationCoverDirector
{
    public function build(string $path, int $size): PublicationCover
    {
        return (new PublicationCover())
            ->setImageSize($size)
            ->setImageName($path)
            ->setUpdatedAt(new \DateTime());
    }
}
