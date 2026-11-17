<?php

declare(strict_types=1);

namespace App\Service\Procedure\Service;

use App\ApiResource\Service\MyServiceAudioInput;
use App\Entity\Service\Service;
use App\Entity\Service\ServiceAudio;
use App\Enum\Service\BeforeAfterSide;
use App\Service\Service\ServiceAudioConverter;
use App\Service\Service\ServiceWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;

/** Stores or removes one side of « Avant, après » (#1410). */
readonly class ServiceAudioProcedure
{
    public function __construct(
        private ServiceAudioConverter $converter,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** Converted before anything is removed, so an unreadable upload leaves the stored sound as it was. */
    public function store(Service $service, BeforeAfterSide $side, MyServiceAudioInput $input): ServiceAudio
    {
        /** @var File $upload validated */
        $upload = $input->file;
        $converted = $this->converter->convert($upload, $upload->getMimeType() ?? '');

        try {
            // Removed and flushed first: the new row would otherwise meet the old one on the unique key.
            $this->remove($service, $side);

            $audio = new ServiceAudio();
            $audio->side = $side;
            $audio->title = ServiceWriter::blankToNull($input->title);
            $audio->credit = ServiceWriter::blankToNull($input->credit);
            $audio->durationSeconds = $converted->durationSeconds;
            $audio->peaks = $converted->peaks;
            $audio->uploadedFile = $converted->file;
            $audio->service = $service;
            $service->audio->add($audio);
            $this->entityManager->persist($audio);
            $this->entityManager->flush();
        } finally {
            if (is_file($converted->file->getPathname())) {
                unlink($converted->file->getPathname());
            }
        }

        return $audio;
    }

    /** VichUploader deletes the stored file with the row. */
    public function remove(Service $service, BeforeAfterSide $side): void
    {
        $audio = $service->audioOn($side);
        if (!$audio instanceof ServiceAudio) {
            return;
        }
        $service->audio->removeElement($audio);
        $this->entityManager->remove($audio);
        $this->entityManager->flush();
    }
}
