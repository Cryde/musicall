<?php

declare(strict_types=1);

namespace App\Service\Procedure\Musician;

use App\ApiResource\Musician\Profile\Media;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Musician\MusicianProfileMedia;
use App\Service\Builder\Musician\MusicianProfileMediaBuilder;
use App\Service\Musician\MediaMetadataFetcher;
use App\Service\Musician\MediaUrlParser;
use Doctrine\ORM\EntityManagerInterface;

readonly class MusicianProfileMediaCreateProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaUrlParser $mediaUrlParser,
        private MusicianProfileMediaBuilder $musicianProfileMediaBuilder,
        private MediaMetadataFetcher $metadataFetcher,
    ) {
    }

    public function handle(Media $mediaDto, MusicianProfile $profile): MusicianProfileMedia
    {
        // URL is already validated by SupportedMediaUrl constraint
        $parsed = $this->mediaUrlParser->parse($mediaDto->url);
        assert($parsed !== null);

        // Fetch metadata from platform (title, thumbnail)
        $metadata = $this->metadataFetcher->fetch($parsed->platform, $mediaDto->url, $parsed->embedId);
        $media = $this->musicianProfileMediaBuilder->build($profile, $parsed, $mediaDto, $metadata);

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }
}
