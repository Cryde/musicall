<?php

declare(strict_types=1);

namespace App\Service\Procedure\Service;

use App\ApiResource\Service\Work\MyServiceWork;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceWork;
use App\Entity\Service\ServiceWorkImage;
use App\Service\Message\ChatMediaPreparer;
use App\Service\Musician\MediaMetadataFetcher;
use App\Service\Musician\MediaUrlParser;
use App\Service\Musician\MediaUrlParser\ParsedMediaUrl;
use App\Service\Service\PositionOrder;
use App\Service\Service\ServiceWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Handler\UploadHandlerInterface;

/** Writes a service's « Réalisations » and their images (#1407). VichUploader deletes a stored image with its row. */
readonly class ServiceWorkProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaUrlParser $mediaUrlParser,
        private MediaMetadataFetcher $metadataFetcher,
        private ChatMediaPreparer $mediaPreparer,
        private UploadHandlerInterface $uploadHandler,
    ) {
    }

    public function create(Service $service, MyServiceWork $input): ServiceWork
    {
        $work = new ServiceWork();
        $work->service = $service;
        $work->position = $this->nextPosition($service->works->toArray());
        $this->write($work, $input);
        $service->works->add($work);
        $this->entityManager->persist($work);
        $this->entityManager->flush();

        return $work;
    }

    public function update(ServiceWork $work, MyServiceWork $input): void
    {
        $this->write($work, $input);
        $this->entityManager->flush();
    }

    public function delete(ServiceWork $work): void
    {
        $work->service->works->removeElement($work);
        $this->entityManager->remove($work);
        $this->entityManager->flush();
    }

    /** @param list<string> $ids */
    public function reorder(Service $service, array $ids): void
    {
        PositionOrder::apply($service->works->toArray(), $ids);
        $this->entityManager->flush();
    }

    public function addImage(ServiceWork $work, File $upload): ServiceWorkImage
    {
        $image = $this->mediaPreparer->image($upload, function (File $converted, string $mimeType, string $name) use ($work): ServiceWorkImage {
            $image = new ServiceWorkImage();
            $image->work = $work;
            $image->position = $this->nextPosition($work->images->toArray());
            $image->mimeType = $mimeType;
            $image->size = $converted->getSize();
            $image->originalName = $name;
            $image->uploadedFile = $converted;
            $work->images->add($image);
            // VichUploader stores the file on persist, while the converted copy still exists.
            $this->entityManager->persist($image);

            return $image;
        });

        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            // Stored the moment it was persisted, so a row that never lands takes its file back.
            $this->uploadHandler->remove($image, 'uploadedFile');
            throw $exception;
        }

        return $image;
    }

    public function removeImage(ServiceWorkImage $image): void
    {
        $image->work->images->removeElement($image);
        $this->entityManager->remove($image);
        $this->entityManager->flush();
    }

    /** @param list<string> $ids */
    public function reorderImages(ServiceWork $work, array $ids): void
    {
        PositionOrder::apply($work->images->toArray(), $ids);
        $this->entityManager->flush();
    }

    private function write(ServiceWork $work, MyServiceWork $input): void
    {
        $work->title = trim($input->title);
        $work->credit = ServiceWriter::blankToNull($input->credit);
        $work->year = $input->year;
        $work->linkUrl = ServiceWriter::blankToNull($input->linkUrl);
        $this->writeMedia($work, ServiceWriter::blankToNull($input->mediaUrl));
    }

    /** The platform is asked for the title only when the link changed. */
    private function writeMedia(ServiceWork $work, ?string $url): void
    {
        if ($url === $work->mediaUrl) {
            return;
        }
        $parsed = $url === null ? null : $this->mediaUrlParser->parse($url);
        if (!$parsed instanceof ParsedMediaUrl) {
            $work->mediaPlatform = null;
            $work->mediaUrl = null;
            $work->mediaEmbedId = null;
            $work->mediaTitle = null;

            return;
        }
        $work->mediaPlatform = $parsed->platform;
        $work->mediaUrl = $url;
        $work->mediaEmbedId = $parsed->embedId;
        $work->mediaTitle = $this->metadataFetcher->fetch($parsed->platform, $url, $parsed->embedId)->title;
    }

    /** @param array<ServiceWork|ServiceWorkImage> $items */
    private function nextPosition(array $items): int
    {
        return $items === [] ? 0 : max(array_map(static fn (ServiceWork|ServiceWorkImage $item): int => $item->position, $items)) + 1;
    }
}
