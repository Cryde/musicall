<?php declare(strict_types=1);

namespace App\State\Processor\User\Course;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Course\UserCourse;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserCourse, void>
 */
class UserCourseDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationRepository $publicationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var UserCourse $data */
        $publication = $this->publicationRepository->find($data->id);

        if ($publication) {
            $this->entityManager->remove($publication);
            $this->entityManager->flush();
        }
    }
}
