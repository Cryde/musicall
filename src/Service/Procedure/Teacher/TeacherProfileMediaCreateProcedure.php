<?php

declare(strict_types=1);

namespace App\Service\Procedure\Teacher;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceMedia;
use App\Service\Builder\Teacher\TeacherProfileMediaBuilder;
use App\Service\Musician\MediaMetadataFetcher;
use App\Validator\Service\ServiceMediaUrlChecker;
use Doctrine\ORM\EntityManagerInterface;

readonly class TeacherProfileMediaCreateProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceMediaUrlChecker $mediaUrlChecker,
        private TeacherProfileMediaBuilder $teacherProfileMediaBuilder,
        private MediaMetadataFetcher $metadataFetcher,
    ) {
    }

    /** A link its kind does not take is a 422 on `url`. */
    public function handle(string $url, ?string $title, Service $profile): ServiceMedia
    {
        $parsed = $this->mediaUrlChecker->check($profile, $url, 'url');

        // Fetch metadata from platform (title, thumbnail)
        $metadata = $this->metadataFetcher->fetch($parsed->platform, $url, $parsed->embedId);
        $media = $this->teacherProfileMediaBuilder->build($profile, $parsed, $url, $title, $metadata);

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }
}
