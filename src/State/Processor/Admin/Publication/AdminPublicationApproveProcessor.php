<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Moderation\ModerationOutcome;
use App\Event\PublicationModeratedEvent;
use App\Repository\PublicationRepository;
use App\Service\Builder\CommentThreadDirector;
use App\Service\Publication\PublicationSlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!($publication = $this->publicationRepository->find($uriVariables['id'])) instanceof \App\Entity\Publication) {
            throw new NotFoundHttpException('Publication not found');
        }

        if ($publication->status !== Publication::STATUS_PENDING) {
            throw new BadRequestHttpException('Only pending publications can be approved');
        }

        $commentThread = $this->commentThreadDirector->create();
        $this->entityManager->persist($commentThread);

        $publication->thread = $commentThread;
        $publication->publicationDatetime = new \DateTime();
        $publication->status = Publication::STATUS_ONLINE;
        $publication->slug = $this->publicationSlug->create($publication->title);

        $moderator = $this->security->getUser();

        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract).
        if ($moderator instanceof User) {
            $this->eventDispatcher->dispatch(new PublicationModeratedEvent($publication, $moderator, ModerationOutcome::Approved));
        }

        return null;
    }
}
