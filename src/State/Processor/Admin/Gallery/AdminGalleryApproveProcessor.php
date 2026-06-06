<?php

declare(strict_types=1);

namespace App\State\Processor\Admin\Gallery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Gallery;
use App\Entity\User;
use App\Enum\Moderation\ModerationOutcome;
use App\Event\GalleryModeratedEvent;
use App\Repository\GalleryRepository;
use App\Service\Publication\GallerySlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class AdminGalleryApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryRepository $galleryRepository,
        private GallerySlug $gallerySlug,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!($gallery = $this->galleryRepository->find($uriVariables['id'])) instanceof \App\Entity\Gallery) {
            throw new NotFoundHttpException('Gallery not found');
        }

        if ($gallery->status !== Gallery::STATUS_PENDING) {
            throw new BadRequestHttpException('Only pending galleries can be approved');
        }

        $gallery->publicationDatetime = new \DateTime();
        $gallery->status = Gallery::STATUS_ONLINE;
        $gallery->slug = $this->gallerySlug->create($gallery->title);

        $moderator = $this->security->getUser();

        $this->entityManager->flush();

        // Best-effort notification dispatched after the commit (epic #689 contract).
        if ($moderator instanceof User) {
            $this->eventDispatcher->dispatch(new GalleryModeratedEvent($gallery, $moderator, ModerationOutcome::Approved));
        }

        return null;
    }
}
