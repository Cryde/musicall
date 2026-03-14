<?php

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\Profile\Media;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Musician\MusicianProfileMedia;
use App\Repository\Musician\MusicianProfileMediaRepository;
use App\Service\File\RemoteFileDownloader;
use App\Service\Musician\MediaMetadata\MediaMetadata;
use App\Service\Musician\MediaUrlParser\ParsedMediaUrl;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class MusicianProfileMediaBuilder
{
    public function __construct(
        private RemoteFileDownloader $remoteFileDownloader,
        private ParameterBagInterface $parameterBag,
        private MusicianProfileMediaRepository $mediaRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function build(
        MusicianProfile $profile,
        ParsedMediaUrl $parsed,
        Media $mediaDto,
        MediaMetadata $metadata,
    ): MusicianProfileMedia
    {
        $media = new MusicianProfileMedia();
        $profileId = $profile->id;
        assert($profileId !== null);

        $media->musicianProfile = $profile;
        $media->platform = $parsed->platform;
        $media->url = $mediaDto->url;
        $media->embedId = $parsed->embedId;
        $media->position = $this->mediaRepository->getNextPosition($profileId);

        // Use user-provided title, or fall back to fetched title
        $media->title = $mediaDto->title ?: $metadata->title;

        // Download and store thumbnail locally
        if ($metadata->thumbnailUrl) {
            $thumbnailImageName = $this->downloadThumbnail($metadata->thumbnailUrl);
            if ($thumbnailImageName) {
                $media->thumbnailImageName = $thumbnailImageName;
            }
        }

        return $media;
    }

    private function downloadThumbnail(string $thumbnailUrl): ?string
    {
        try {
            /** @var string $destination */
            $destination = $this->parameterBag->get('file_musician_media_thumbnail_destination');
            [$filename] = $this->remoteFileDownloader->download($thumbnailUrl, $destination, validateChecksum: false);

            return $filename;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to download media thumbnail', [
                'thumbnailUrl' => $thumbnailUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
