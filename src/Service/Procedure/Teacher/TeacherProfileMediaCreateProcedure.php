<?php

declare(strict_types=1);

namespace App\Service\Procedure\Teacher;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceMedia;
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

    /** @param string $url already validated by the SupportedMediaUrl constraint */
    public function handle(string $url, ?string $title, Service $profile): ServiceMedia
    {
        $parsed = $this->mediaUrlParser->parse($url);
        if (!$parsed instanceof \App\Service\Musician\MediaUrlParser\ParsedMediaUrl) {
            throw new \RuntimeException('Invalid media URL');
        }

        // Fetch metadata from platform (title, thumbnail)
        $metadata = $this->metadataFetcher->fetch($parsed->platform, $url, $parsed->embedId);
        $media = $this->teacherProfileMediaBuilder->build($profile, $parsed, $url, $title, $metadata);

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }
}
