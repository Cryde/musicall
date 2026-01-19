<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\Builder\CommentThreadDirector;
use App\Service\Publication\PublicationSlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class AdminPublicationApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicationRepository $publicationRepository,
        private PublicationSlug $publicationSlug,
        private CommentThreadDirector $commentThreadDirector,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$publication = $this->publicationRepository->find($uriVariables['id'])) {
            throw new NotFoundHttpException('Publication not found');
        }

        if ($publication->getStatus() !== Publication::STATUS_PENDING) {
            throw new BadRequestHttpException('Only pending publications can be approved');
        }

        $commentThread = $this->commentThreadDirector->create();
        $this->entityManager->persist($commentThread);

        $publication->setThread($commentThread);
        $publication->setPublicationDatetime(new \DateTime());
        $publication->setStatus(Publication::STATUS_ONLINE);
        $publication->setSlug($this->publicationSlug->create((string) $publication->getTitle()));

        $this->entityManager->flush();

        return null;
    }
}
