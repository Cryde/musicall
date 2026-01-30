<?php

declare(strict_types=1);

namespace App\State\Processor\Teacher;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Repository\Teacher\TeacherProfileMediaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<TeacherProfileMedia, void>
 */
readonly class TeacherProfileMediaDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeacherProfileMediaRepository $mediaRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var TeacherProfileMedia $data */
        $media = $this->mediaRepository->find($data->id);

        if ($media) {
            $this->entityManager->remove($media);
            $this->entityManager->flush();
        }
    }
}
