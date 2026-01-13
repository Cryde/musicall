<?php

declare(strict_types=1);

namespace App\State\Processor\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Musician\MusicianProfileMedia;
use App\Repository\Musician\MusicianProfileMediaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<MusicianProfileMedia, void>
 */
readonly class MusicianProfileMediaDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MusicianProfileMediaRepository $mediaRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var MusicianProfileMedia $data */
        $media = $this->mediaRepository->find($data->id);

        if ($media) {
            $this->entityManager->remove($media);
            $this->entityManager->flush();
        }
    }
}
