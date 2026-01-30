<?php

declare(strict_types=1);

namespace App\Service\Procedure\Teacher;

use App\ApiResource\Teacher\Profile\Media;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfileMedia;
use App\Service\Builder\Teacher\TeacherProfileMediaBuilder;
use App\Service\Musician\MediaMetadataFetcher;
use App\Service\Musician\MediaUrlParser;
use Doctrine\ORM\EntityManagerInterface;

readonly class TeacherProfileMediaCreateProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaUrlParser $mediaUrlParser,
        private TeacherProfileMediaBuilder $teacherProfileMediaBuilder,
        private MediaMetadataFetcher $metadataFetcher,
    ) {
    }

    public function handle(Media $mediaDto, TeacherProfile $profile): TeacherProfileMedia
    {
        // URL is already validated by SupportedMediaUrl constraint
        $parsed = $this->mediaUrlParser->parse($mediaDto->url);
        if ($parsed === null) {
            throw new \RuntimeException('Invalid media URL');
        }

        // Fetch metadata from platform (title, thumbnail)
        $metadata = $this->metadataFetcher->fetch($parsed->platform, $mediaDto->url, $parsed->embedId);
        $media = $this->teacherProfileMediaBuilder->build($profile, $parsed, $mediaDto, $metadata);

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }
}
