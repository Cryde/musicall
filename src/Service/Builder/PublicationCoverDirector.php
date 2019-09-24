<?php

namespace App\Service\Builder;

use App\Entity\Image\PublicationCover;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PublicationCoverDirector
{
    public function build(UploadedFile $file)
    {
        return (new PublicationCover())
            ->setImageSize($file->getSize())
            ->setImageName($file->getBasename())
            ->setUpdatedAt(new \DateTimeImmutable());
    }
}
